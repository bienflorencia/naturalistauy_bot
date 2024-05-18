<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * @todo: add support for pagination!!
 */
class Naturalist
{
    public const BASE_URL = 'https://api.inaturalist.org';

    public function __construct()
    {
        //
    }

    public function getTaxonCount(array $taxonIds, int $placeId = 7259): Collection
    {
        $url = self::BASE_URL . '/v1/observations/species_counts';
        $params = [
            'place_id' => $placeId,
            'taxon_id' => implode(',', $taxonIds),
        ];

        $response = Http::get($url, $params);

        return collect($response->json('results'));
    }

    /**
     * Get all observations created on the given date
     *
     * @param string $createdOn ''
     * @param integer $placeId
     * @return Collection
     */
    public function getObservationsCreatedOn(string $createdOn, int $placeId = 7259): Collection
    {
        $url = self::BASE_URL . '/v1/observations/';
        $params = [
            'place_id' => $placeId,
            'created_on' => $createdOn,
            'lrank' => 'species',
            'quality_grade' => 'research',
            'locale' => 'es-AR',
            'preferred_place_id' => $placeId,
        ];

        $response = Http::get($url, $params);

        return collect($response->json('results'));
    }
}
