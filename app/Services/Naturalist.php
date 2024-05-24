<?php

namespace App\Services;

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
            'per_page' => 500,
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
            'per_page' => 200,
        ];

        $response = Http::get($url, $params);

        return collect($response->json('results'));
    }

    public function getTopIdentifiers(string $from, string $to, int $taxonId, int $placeId = 7259)
    {

        $url = self::BASE_URL . '/v1/identifications/identifiers';
        $params = [
            'own_observation' => false,
            'place_id' => $placeId,
            'iconic_taxon_id' => $taxonId,
            'd1' => $from,
            'd2' => $to,
            'order' => 'desc',
            'per_page' => 5,
        ];

        $response = Http::get($url, $params);

        return collect($response->json('results'));
    }

    public function getEmojiForIconicTaxa(string $iconicTaxa) : string
    {
        $table = [
            'Plantae' => '🌴',
            'Aves' => '🦜',
            'Insecta' => '🪲',
            'Arachnida' => '🕷️',
            'Fungi' => '🍄',
            'Mammalia' => '🦨',
            'Amphibia' => '🐸',
            'Reptilia' => '🦎',
            'Animalia' => '🪼',
            'Mollusca' => '🐌',
            'Actinopterygii' => '🐟',
            'Protozoa' => '🧬',
            'Chromista' => '🦠',
        ];

        if (!isset($table[$iconicTaxa])) {
            return '';
        }

        return $table[$iconicTaxa];
    }

    public function getNameForIconicTaxaId(int $taxaId) : string
    {
        $table = [
            1 => 'Animalia',
            3 => 'Aves',
            47126 => 'Plantae',
            47158 => 'Insecta',
            47119 => 'Arachnida',
            47170 => 'Fungi',
            40151 => 'Mammalia',
            20978 => 'Amphibia',
            26036 => 'Reptilia',
            47115 => 'Mollusca',
            47178 => 'Actinopterygii',
            47686 => 'Protozoa ',
            48222 => 'Chromista',
        ];

        if (!isset($table[$taxaId])) {
            return '';
        }

        return $table[$taxaId];
    }

    public function getCommonNameForIconicTaxa(string $iconicTaxa) : string
    {
        $table = [
            'Plantae' => 'plantas',
            'Aves' => 'aves',
            'Insecta' => 'insectos',
            'Arachnida' => 'arácnidos',
            'Fungi' => 'hongos',
            'Mammalia' => 'mamíferos',
            'Amphibia' => 'anfibios',
            'Reptilia' => 'reptiles',
            'Animalia' => 'otros animales',
            'Mollusca' => 'moluscos',
            'Actinopterygii' => 'peces con aletas radiadas',
            'Protozoa' => 'protozoarios',
            'Chromista' => 'algas pardas',
        ];

        if (!isset($table[$iconicTaxa])) {
            return '';
        }

        return $table[$iconicTaxa];
    }
}
