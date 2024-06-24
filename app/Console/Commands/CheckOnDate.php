<?php

namespace App\Console\Commands;

use App\Services\Naturalist;
use App\Services\Tacuruses;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CheckOnDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iNat:check-on-date {date} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle(Tacuruses $fediApi, Naturalist $natuApi)
    {
        $date = Carbon::parse($this->argument('date'));

        // Get all observations made ON to this date
        $observationsOnDate = $natuApi->getObservationsCreatedOn($date->format('Y-m-d'))
            ->reject(
                fn (array $observation) => Arr::get($observation, 'taxon.rank') !== 'species'
            )->map(fn (array $observation) => [
                'id' => $observation['id'],
                'taxon_id' => Arr::get($observation, 'taxon.id'),
                'taxon_name' => Arr::get($observation, 'taxon.name'),
                'iconic_taxa' => Arr::get($observation, 'taxon.iconic_taxon_name'),
                'common_name' => Arr::get($observation, 'taxon.preferred_common_name'),
                'observed_on' => Carbon::parse($observation['time_observed_at']),
                'url' => str_replace('inaturalist.org', 'naturalista.uy', $observation['uri']),
                'username' => Arr::get($observation, 'user.login'),
                'photos' => $observation['photos'],
                'threatened' => Arr::get($observation, 'taxon.threatened'),
                'introduced' => Arr::get($observation, 'taxon.introduced'),
                'native' => Arr::get($observation, 'taxon.native'),
            ]);

        // Get the count for each of the taxons
        $observationsCountOnPlace = $natuApi->getTaxonCount(
            $observationsOnDate->pluck('taxon_id')->toArray()
        )->map(fn (array $observation) => [
            'taxon_id' => Arr::get($observation, 'taxon.id'),
            'count_natUY' => Arr::get($observation, 'count', 0),
        ])->sortBy('count_natUY');

        $observationMostRareCountOnPlace = $observationsCountOnPlace->first();

        $observationMostRareCountOnPlatform = $natuApi->getTaxonCount(
            [$observationMostRareCountOnPlace['taxon_id']],
            null
        )->map(fn (array $observation) => [
            'taxon_id' => Arr::get($observation, 'taxon.id'),
            'count_iNat' => Arr::get($observation, 'count', 0),
        ])->first();

        $mostRare = $observationsOnDate->firstWhere('taxon_id', '=', $observationMostRareCountOnPlace['taxon_id']);

        $countPlace = $observationMostRareCountOnPlace['count_natUY'];
        $countWorld = $observationMostRareCountOnPlatform['count_iNat'] - $observationMostRareCountOnPlace['count_natUY'];

        // Introducción
        $message = "<p><a href=\"{$mostRare['url']}\">Registro del día en 🇺🇾</a><br>";
        $message .= 'Esta fue la especie con menos observaciones registradas en Uruguay el ' .
            $date->locale('es_UY')->isoFormat('dddd [pasado]') .
            ' (' . $date->format('Y-m-d') . '):<br><br><b>';

        // Nombre de especie
        if (!empty($mostRare['common_name'])) {
            $message .= "{$mostRare['common_name']} (<i>{$mostRare['taxon_name']}</i>), ";
        } else {
            $message .= "<i>{$mostRare['taxon_name']}</i>, ";
        }
        $message .= $mostRare['iconic_taxa'] . ' ' . $natuApi->getEmojiForIconicTaxa($mostRare['iconic_taxa']) . '.</b><br><br>';

        // Info de la observación (usuario y fecha)
        $message .= '<blockquote>Observada por <a href="https://www.naturalista.uy/people/' . $mostRare['username'] . '">' . $mostRare['username'] . '</a> el ';
        $message .= $mostRare['observed_on']->locale('es_UY')->isoFormat('dddd D [de] MMMM [de] YYYY') . '.</blockquote><br>';

        // Info de la especie
        $message .= 'Esta especie ';
        if ($mostRare['native']) {
            $message .= 'es nativa de Uruguay';
        } elseif ($mostRare['introduced']) {
            $message .= 'fue introducida en Uruguay';
        }

        if ($mostRare['threatened']) {
            if ($mostRare['native'] || $mostRare['introduced']) {
                $message .= ', ';
            }

            $message .= 'está amenazada y ';
        } elseif ($mostRare['native'] || $mostRare['introduced']) {
            $message .= ' y ';
        }

        // Info del número de registros
        if ($countPlace === 1) {
            if ($mostRare['introduced']) {
                $message .= 'hasta ahora no tenía registros en el país';
                if ($countWorld === 0) {
                    // primera vez en Uruguay en el mundo
                    $message .= ' (¡ni en el mundo! 😲)';
                } elseif ($countWorld >= 1) {
                    // primera vez en Uruguay pero no en el mundo
                    $message .= ' (aunque se registró ' . $countWorld . ' ' . Str::plural('vez', $countWorld) . ' en el resto del mundo)';
                }
            } else {
                $message .= '<b>¡es la primera vez que se registra en el país';
                if ($countWorld === 0) {
                    // primera vez en Uruguay en el mundo
                    $message .= ' y en el mundo';
                } elseif ($countWorld >= 1) {
                    // primera vez en Uruguay pero no en el mundo
                    $message .= '</b> (aunque se registró ' . $countWorld . ' ' . Str::plural('vez', $countWorld) . ' en el resto del mundo)!';
                } else {
                    // no debería pasar pero ¯\_(ツ)_/¯
                    $message .= '!</b>';
                }
            }

        } else {
            $message .= 'ha sido registrada ' . $countPlace;
            $message .= ' ' . Str::plural('vez', $countPlace) . ' en el país';
            if ($countWorld > 0) {
                $message .= ' y ' . $countWorld . ' ' . Str::plural('vez', $countWorld) . ' más en el resto del mundo';
            } else {
                $message .= ' y no tiene registros afuera de Uruguay.';
            }
            $message .= '.';
        }
        $message .= '</p>';

        if ($this->option('dry-run')) {
            $this->info(str_replace('<br>', PHP_EOL, $message));
        } else {
            $fediApi->publishPost($message);
        }

    }
}
