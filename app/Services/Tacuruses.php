<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class Tacuruses
{
    private const STATUSES = '/api/v1/statuses';
    private const MEDIA = '/api/v2/media';

    public function __construct(private string $host, private string $apiKey)
    {
        //
    }

    public function publishPost(string $message, array $options = []): Response
    {
        $data = [
            'status' => $message,
            'visibility' => 'unlisted',
        ];
        $data = array_merge($data, $options);
        return $this->getClient()->asJson()->post(self::STATUSES, $data);
    }

    public function uploadMedia(array $options): Response
    {
        $file = Arr::get($options, 'file');
        return $this->getClient()
            ->attach('file', $file, 'pic.jpg')
            ->withoutRedirecting()
            ->post(self::MEDIA, $options);
    }

    private function getClient(): PendingRequest
    {
        return Http::baseUrl($this->host)->withToken($this->apiKey);
    }
}
