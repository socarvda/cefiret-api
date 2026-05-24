<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    protected Client $client;
    protected ?Calendar $service = null;
    protected string $calendarId;
    protected string $tokenPath;

    public function __construct()
    {
        $this->tokenPath = storage_path('app/google-calendar-token.json');
        $this->calendarId = env('GOOGLE_CALENDAR_ID', 'primary');

        $this->client = new Client();
        $this->client->setApplicationName('CEFIRET');
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $this->client->addScope(Calendar::CALENDAR);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');

        $this->loadStoredToken();
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function handleCallback(string $code): void
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \Exception('Error obteniendo token: ' . ($token['error_description'] ?? $token['error']));
        }

        $this->client->setAccessToken($token);
        $this->saveToken($token);

        $this->service = new Calendar($this->client);
    }

    public function isAuthenticated(): bool
    {
        if (!$this->service) {
            return false;
        }

        if ($this->client->isAccessTokenExpired()) {
            return $this->refreshToken();
        }

        return true;
    }

    public function revokeToken(): void
    {
        if (file_exists($this->tokenPath)) {
            $this->client->revokeToken();
            unlink($this->tokenPath);
        }

        $this->service = null;
    }

    public function createEvent(array $data): ?string
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        try {
            $horaFin = date('H:i', strtotime($data['hora'] . ' +1 hour'));
            $timezone = env('APP_TIMEZONE', 'America/Mexico_City');

            $event = new Event([
                'summary' => '🩺 Cita: ' . $data['paciente'],
                'description' =>
                    "Fisioterapeuta: {$data['fisioterapeuta']}\n" .
                    "Motivo: " . ($data['motivo'] ?? 'Sin motivo') . "\n" .
                    "ID Cita: " . ($data['id_cita'] ?? ''),
                'start' => new EventDateTime([
                    'dateTime' => $data['fecha'] . 'T' . $data['hora'] . ':00',
                    'timeZone' => $timezone,
                ]),
                'end' => new EventDateTime([
                    'dateTime' => $data['fecha'] . 'T' . $horaFin . ':00',
                    'timeZone' => $timezone,
                ]),
                'colorId' => '2',
            ]);

            $created = $this->service->events->insert($this->calendarId, $event);

            return $created->getId();
        } catch (\Exception $e) {
            Log::error('GoogleCalendar createEvent: ' . $e->getMessage());
            return null;
        }
    }

    public function deleteEvent(string $googleEventId): void
    {
        if (!$this->isAuthenticated()) {
            return;
        }

        try {
            $this->service->events->delete($this->calendarId, $googleEventId);
        } catch (\Exception $e) {
            Log::warning('GoogleCalendar deleteEvent: ' . $e->getMessage());
        }
    }

    public function cancelEvent(string $googleEventId): void
    {
        if (!$this->isAuthenticated()) {
            return;
        }

        try {
            $event = $this->service->events->get($this->calendarId, $googleEventId);
            $summary = $event->getSummary();

            if (!str_starts_with((string) $summary, 'CANCELADA - ')) {
                $event->setSummary('CANCELADA - ' . $summary);
            }

            $event->setColorId('4');

            $this->service->events->update($this->calendarId, $googleEventId, $event);
        } catch (\Exception $e) {
            Log::warning('GoogleCalendar cancelEvent: ' . $e->getMessage());
        }
    }

    public function getBusySlots(string $fecha): array
    {
        if (!$this->isAuthenticated()) {
            return [];
        }

        try {
            $timezone = env('APP_TIMEZONE', 'America/Mexico_City');
            $timeZone = new \DateTimeZone($timezone);

            $startOfDay = new \DateTime($fecha . ' 00:00:00', $timeZone);
            $endOfDay = new \DateTime($fecha . ' 23:59:59', $timeZone);

            $events = $this->service->events->listEvents($this->calendarId, [
                'timeMin' => $startOfDay->format(\DateTime::RFC3339),
                'timeMax' => $endOfDay->format(\DateTime::RFC3339),
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);

            $busySlots = [];

            foreach ($events->getItems() as $event) {
                $start = $event->getStart()->getDateTime();

                if ($start) {
                    $busySlots[] = date('H:i', strtotime($start));
                }
            }

            return array_values(array_unique($busySlots));
        } catch (\Exception $e) {
            Log::error('GoogleCalendar getBusySlots: ' . $e->getMessage());
            return [];
        }
    }

    private function loadStoredToken(): void
    {
        if (!file_exists($this->tokenPath)) {
            return;
        }

        $token = json_decode(file_get_contents($this->tokenPath), true);

        if (!$token) {
            return;
        }

        $this->client->setAccessToken($token);
        $this->service = new Calendar($this->client);

        if ($this->client->isAccessTokenExpired()) {
            $this->refreshToken();
        }
    }

    private function refreshToken(): bool
    {
        $oldToken = $this->client->getAccessToken();
        $refreshToken = $this->client->getRefreshToken();

        if (!$refreshToken && isset($oldToken['refresh_token'])) {
            $refreshToken = $oldToken['refresh_token'];
        }

        if (!$refreshToken) {
            return false;
        }

        try {
            $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newToken['error'])) {
                Log::error('GoogleCalendar refreshToken error: ' . json_encode($newToken));
                return false;
            }

            if (!isset($newToken['refresh_token'])) {
                $newToken['refresh_token'] = $refreshToken;
            }

            $this->client->setAccessToken($newToken);
            $this->saveToken($newToken);

            $this->service = new Calendar($this->client);

            return true;
        } catch (\Exception $e) {
            Log::error('GoogleCalendar refreshToken: ' . $e->getMessage());
            return false;
        }
    }

    private function saveToken(array $token): void
    {
        $directory = dirname($this->tokenPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($this->tokenPath, json_encode($token));
    }
}
