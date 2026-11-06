<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bandeja de mensajes de la subcuenta de GHL, leída en vivo: el sistema no
 * guarda copia de las conversaciones, así que GHL sigue siendo la fuente y
 * Cata sigue funcionando igual. Ver una conversación aquí NO la marca como
 * leída en GHL.
 */
class GhlConversations
{
    private const BASE_URL = 'https://services.leadconnectorhq.com';

    private const API_VERSION = '2021-04-15';

    private const CONTACTS_API_VERSION = '2021-07-28';

    private const TZ = 'America/Santiago';

    /** Canal de la bandeja => [tipo de mensaje en GHL, tipo para enviar (null = solo lectura)]. */
    public const CHANNELS = [
        'whatsapp' => ['TYPE_WHATSAPP', 'WhatsApp'],
        'instagram' => ['TYPE_INSTAGRAM', 'IG'],
        'facebook' => ['TYPE_FACEBOOK', 'FB'],
        'email' => ['TYPE_EMAIL', null],
    ];

    /** Horas que WhatsApp deja responder libremente desde el último mensaje del cliente. */
    public const WHATSAPP_WINDOW_HOURS = 24;

    private readonly string $token;

    private readonly string $locationId;

    public function __construct(?string $token = null, ?string $locationId = null)
    {
        $this->token = $token ?? (string) config('services.ghl.private_token');
        $this->locationId = $locationId ?? (string) config('services.ghl.location_id');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->locationId !== '';
    }

    /** La versión va aquí y no después: withHeaders suma valores y GHL rechaza dos versiones. */
    private function http(string $version = self::API_VERSION): PendingRequest
    {
        return Http::withToken($this->token)
            ->withHeaders(['Version' => $version, 'Accept' => 'application/json'])
            ->baseUrl((string) config('services.ghl.base_url', self::BASE_URL))
            ->timeout(10);
    }

    /**
     * @param  string  $view  por_atender (lo último lo escribió el cliente), esperando (lo último es nuestro) o todas
     * @return array{items: array<int, array<string, mixed>>, next: ?int}
     */
    public function search(string $view = 'todas', ?string $channel = null, ?string $query = null, ?int $before = null): array
    {
        $direction = ['por_atender' => 'inbound', 'esperando' => 'outbound'][$view] ?? null;
        $messageType = self::CHANNELS[$channel][0] ?? null;

        $response = $this->http()->get('/conversations/search', array_filter([
            'locationId' => $this->locationId,
            'limit' => 30,
            'sortBy' => 'last_message_date',
            'sort' => 'desc',
            'query' => $query ?: null,
            'lastMessageDirection' => $direction,
            'lastMessageType' => $messageType,
            'startAfterDate' => $before,
        ]))->throw();

        $raw = (array) $response->json('conversations', []);
        $last = end($raw) ?: null;

        // GHL ignora en silencio los filtros que no reconoce: se aplican
        // también aquí para que la bandeja nunca muestre otra cosa.
        $items = collect($raw)
            ->map(fn (array $c) => $this->summary($c))
            ->filter(fn (array $c) => (! $direction || $c['direction'] === $direction) && (! $channel || $c['channel'] === $channel))
            ->values()
            ->all();

        return ['items' => $items, 'next' => count($raw) === 30 ? ($last['lastMessageDate'] ?? null) : null];
    }

    /** @return array<string, mixed> */
    private function summary(array $c): array
    {
        $date = $c['lastMessageDate'] ?? null;

        return [
            'id' => (string) ($c['id'] ?? ''),
            'contact_id' => (string) ($c['contactId'] ?? ''),
            'name' => trim((string) ($c['fullName'] ?? $c['contactName'] ?? '')) ?: ((string) ($c['phone'] ?? $c['email'] ?? 'Sin nombre')),
            'channel' => $this->channelOf((string) ($c['lastMessageType'] ?? $c['type'] ?? '')),
            'preview' => (string) ($c['lastMessageBody'] ?? ''),
            'direction' => (string) ($c['lastMessageDirection'] ?? ''),
            'unread' => (int) ($c['unreadCount'] ?? 0),
            'when' => $date ? $this->shortDate(Carbon::createFromTimestampMs($date)) : '',
        ];
    }

    /**
     * Mensajes de la conversación, del más antiguo al más nuevo.
     *
     * @return array{contact_id: string, messages: array<int, array<string, mixed>>, channel: ?string, window_left_minutes: ?int, can_reply: bool, reply_blocked: ?string}
     */
    public function thread(string $conversationId): array
    {
        $conversation = (array) $this->http()->get("/conversations/{$conversationId}")->throw()->json();
        $conversation = (array) ($conversation['conversation'] ?? $conversation);

        $raw = (array) $this->http()->get("/conversations/{$conversationId}/messages", ['limit' => 50])
            ->throw()->json('messages.messages', []);

        $messages = collect($raw)
            ->filter(fn (array $m) => ! str_starts_with((string) ($m['messageType'] ?? ''), 'TYPE_ACTIVITY') && ($this->text($m) !== '' || ! empty($m['attachments'])))
            ->sortBy(fn (array $m) => (string) ($m['dateAdded'] ?? ''))
            ->values();

        $lastInbound = $messages->last(fn (array $m) => ($m['direction'] ?? '') === 'inbound');
        $channel = $this->channelOf((string) ($lastInbound['messageType'] ?? $messages->last()['messageType'] ?? $conversation['type'] ?? ''));

        return [
            'contact_id' => (string) ($conversation['contactId'] ?? ''),
            'messages' => $messages->map(fn (array $m) => [
                'id' => (string) ($m['id'] ?? ''),
                'mine' => ($m['direction'] ?? '') !== 'inbound',
                'body' => $this->text($m),
                'attachments' => array_values(array_filter((array) ($m['attachments'] ?? []), fn ($url) => is_string($url) && str_starts_with($url, 'https://'))),
                'when' => isset($m['dateAdded']) ? $this->shortDate(Carbon::parse($m['dateAdded'])) : '',
                'status' => (string) ($m['status'] ?? ''),
            ])->all(),
        ] + $this->replyState($channel, $lastInbound['dateAdded'] ?? null);
    }

    /** @return array{channel: ?string, window_left_minutes: ?int, can_reply: bool, reply_blocked: ?string} */
    private function replyState(?string $channel, ?string $lastInboundAt): array
    {
        $state = ['channel' => $channel, 'window_left_minutes' => null, 'can_reply' => true, 'reply_blocked' => null];

        if (! $channel || (self::CHANNELS[$channel][1] ?? null) === null) {
            return ['can_reply' => false, 'reply_blocked' => 'Este canal se responde desde GHL por ahora.'] + $state;
        }

        if ($channel === 'whatsapp') {
            $left = $lastInboundAt
                ? (int) floor(now()->diffInMinutes(Carbon::parse($lastInboundAt)->addHours(self::WHATSAPP_WINDOW_HOURS), false))
                : 0;
            $state['window_left_minutes'] = max(0, $left);

            if ($left <= 0) {
                return ['can_reply' => false, 'reply_blocked' => 'Pasaron más de 24 horas desde su último mensaje: WhatsApp solo permite escribirle con una plantilla aprobada.'] + $state;
            }
        }

        return $state;
    }

    /**
     * Envía por el mismo canal de la conversación. El contacto y el canal
     * salen siempre de GHL, nunca de lo que mande el navegador.
     */
    public function reply(string $conversationId, string $message): string
    {
        $thread = $this->thread($conversationId);

        if (! $thread['can_reply'] || $thread['contact_id'] === '') {
            throw new RuntimeException($thread['reply_blocked'] ?? 'No se pudo identificar a quién responder.');
        }

        $this->http()->post('/conversations/messages', [
            'type' => self::CHANNELS[$thread['channel']][1],
            'contactId' => $thread['contact_id'],
            'message' => $message,
        ])->throw();

        return (string) $thread['channel'];
    }

    /** @return array<string, mixed> */
    public function contact(string $contactId): array
    {
        $contact = (array) $this->http(self::CONTACTS_API_VERSION)->get("/contacts/{$contactId}")->throw()->json('contact', []);

        return [
            'name' => trim(($contact['firstName'] ?? '').' '.($contact['lastName'] ?? '')) ?: (string) ($contact['contactName'] ?? ''),
            'phone' => (string) ($contact['phone'] ?? ''),
            'email' => (string) ($contact['email'] ?? ''),
            'tags' => array_values((array) ($contact['tags'] ?? [])),
        ];
    }

    private function channelOf(string $type): ?string
    {
        return match (true) {
            str_contains($type, 'WHATSAPP') => 'whatsapp',
            str_contains($type, 'INSTAGRAM') => 'instagram',
            str_contains($type, 'FACEBOOK'), str_contains($type, 'FB_') => 'facebook',
            str_contains($type, 'EMAIL') => 'email',
            default => null,
        };
    }

    private function text(array $message): string
    {
        return trim((string) ($message['body'] ?? ''));
    }

    private function shortDate(Carbon $date): string
    {
        $date = $date->copy()->timezone(self::TZ);

        return $date->isSameDay(now(self::TZ)) ? $date->format('H:i') : $date->format('d/m H:i');
    }
}
