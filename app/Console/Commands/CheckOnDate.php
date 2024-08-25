<?php

namespace App\Console\Commands;

use App\Services\Naturalist;
use App\Services\Tacuruses;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    private Tacuruses $fediApi;
    private Naturalist $natuApi;
    private Carbon $date;
    private int $messagesPublished = 0;
    private int $lastMessageSentId = 0;

    private const URUGUAY_PLACE_ID = 7259;

    /**
     * Execute the console command.
     */
    public function handle(Tacuruses $fediApi, Naturalist $natuApi)
    {
        $this->fediApi = $fediApi;
        $this->natuApi = $natuApi;

        $this->date = Carbon::parse($this->argument('date'));

        // Get all observations made ON to this date
        $observationsOnDate = $this->natuApi->getObservationsCreatedOn($this->date->format('Y-m-d'))
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

        // Get the count for each of the taxons for Uruguay
        $observationsCountOnPlace = $this->natuApi->getTaxonCount(
            $observationsOnDate->pluck('taxon_id')->toArray(),
            self::URUGUAY_PLACE_ID
        )->map(fn (array $observation) => [
            'taxon_id' => Arr::get($observation, 'taxon.id'),
            'count_natUY' => Arr::get($observation, 'count', 0),
        ])->sortBy('count_natUY');

        // Get the observation(s) with the less count_natUY
        $rarestObservationsOnPlace = $observationsCountOnPlace->filter(
            fn (array $observation) => Arr::get($observation, 'count_natUY', true) === Arr::get($observationsCountOnPlace->first(), 'count_natUY', false)
        )->values();

        // Now, retrieve the count for the whole platform for the observation(s)
        // on the place with the minimum count (maybe one or more)
        $rarestObservationsOnWholePlatform = $this->natuApi->getTaxonCount(
            $rarestObservationsOnPlace->pluck('taxon_id')->toArray(),
            null
        )->map(fn (array $observation) => [
            'taxon_id' => Arr::get($observation, 'taxon.id'),
            'count_iNat' => Arr::get($observation, 'count', 0),
        ]);

        if ($rarestObservationsOnPlace->count() === 1) {
            $observation = $rarestObservationsOnPlace->first();
            $mostRare = $observationsOnDate->firstWhere('taxon_id', '=', $observation['taxon_id']);
            $countPlace = $observation['count_natUY'];
            $countWorld = $rarestObservationsOnWholePlatform->firstWhere('taxon_id', '=', $observation['taxon_id'])['count_iNat'] - $observation['count_natUY'];
            $this->composeMessageForSingleObservation($mostRare, $countPlace, $countWorld);
        } else {
            $this->composeMessageForMultipleObservations($observationsOnDate, $rarestObservationsOnPlace, $rarestObservationsOnWholePlatform);
        }
    }

    private function composeMessageForSingleObservation(array $mostRare, int $countPlace, int $countWorld) : void
    {
        // Introducción
        $message = "<p><a href=\"{$mostRare['url']}\">Registro del día en 🇺🇾</a><br>";

        $message .= 'Esta fue la especie con menos observaciones registradas en Uruguay el ' .
            $this->date->locale('es_UY')->isoFormat('dddd [pasado]') .
            ' (' . $this->date->format('Y-m-d') . '):<br><br>';

        $message .= $this->getNameForMessage($mostRare) . '<br><br>';

        // Info de la observación (usuario y fecha)
        $message .= '<blockquote>';
        $message .= $this->getObservationInfoForMessage($mostRare);
        $message .= '</blockquote><br>';

        // Info de la especie
        $message .= $this->getSpeciesInfoForMessage($mostRare, $countPlace, $countWorld);
        $message .= '</p>';

        if ($this->option('dry-run')) {
            $this->info(str_replace('<br>', PHP_EOL, $message) . PHP_EOL . PHP_EOL);
        } else {
            $this->fediApi->publishPost($message);
        }
        $this->messagesPublished++;
    }

    private function composeMessageForMultipleObservations(Collection $observationsOnDate, Collection $rarestObservationsOnPlace, Collection $rarestObservationsOnWholePlatform) : void
    {

        $message = '<p><b>Registros del día en 🇺🇾</b><br><br>';
        $message .= 'Estas fueron las especies con menos observaciones registradas en Uruguay el ' .
            $this->date->locale('es_UY')->isoFormat('dddd [pasado]') .
            ' (' . $this->date->format('Y-m-d') . '):<br><ul>';

        $rarestObservationsOnPlace->each(function (array $rareObservation) use (&$message, $observationsOnDate) : void {
            $observation = $observationsOnDate->firstWhere('taxon_id', '=', $rareObservation['taxon_id']);
            $message .= '<li>';
            $message .= $this->getNameForMessage($observation);
            $message .= '</li>';
        });
        $message .= '</ul><br>🧵 1/' . $rarestObservationsOnPlace->count() + 1 . '</p>';

        // First message of thread, intro
        if ($this->option('dry-run')) {
            $this->info(str_replace('<br>', PHP_EOL, $message) . PHP_EOL . PHP_EOL);
        } else {
            $response = $this->fediApi->publishPost($message);
            $this->lastMessageSentId = $response->json('id');
        }
        $this->messagesPublished++;

        $rarestObservationsOnPlace->each(function (array $rareObservation, int $key) use ($observationsOnDate, $rarestObservationsOnWholePlatform): void {
            $observation = $observationsOnDate->firstWhere('taxon_id', '=', $rareObservation['taxon_id']);
            $countPlace = $rareObservation['count_natUY'];
            $countWorld = $rarestObservationsOnWholePlatform->firstWhere('taxon_id', '=', $rareObservation['taxon_id'])['count_iNat'] - $rareObservation['count_natUY'];

            $message = "<p><a href=\"{$observation['url']}\">";
            $message .= $this->getNameForMessage($observation) . '</a><br>';
            $message .= '<blockquote>';
            $message .= $this->getObservationInfoForMessage($observation);
            $message .= '</blockquote>';

            // Info de la especie
            $message .= $this->getSpeciesInfoForMessage($observation, $countPlace, $countWorld);
            $message .= '<br><br>🧵 ' . ($key + 2) . '/' . ($rarestObservationsOnWholePlatform->count() + 1) . '</p>';

            // Publish
            if ($this->option('dry-run')) {
                $this->info(str_replace('<br>', PHP_EOL, $message) . PHP_EOL . PHP_EOL);
            } else {
                $options['in_reply_to_id'] = $this->lastMessageSentId;
                $response = $this->fediApi->publishPost($message, $options);
                $this->lastMessageSentId = $response->json('id');
            }
            $this->messagesPublished++;

        });

    }

    private function getNameForMessage(array $observation) : string
    {
        $message = '<b>';
        // Nombre de especie
        if (!empty($observation['common_name'])) {
            $message .= "{$observation['common_name']} (<i>{$observation['taxon_name']}</i>), ";
        } else {
            $message .= "<i>{$observation['taxon_name']}</i>, ";
        }
        $message .= $observation['iconic_taxa'] . ' ' . $this->natuApi->getEmojiForIconicTaxa($observation['iconic_taxa']) . '.</b>';

        return $message;
    }

    private function getObservationInfoForMessage(array $observation) : string
    {
        $message = 'Observada por <a href="https://www.naturalista.uy/people/' . $observation['username'] . '">' . $observation['username'] . '</a> el ';
        $message .= $observation['observed_on']->locale('es_UY')->isoFormat('dddd D [de] MMMM [de] YYYY') . '.';

        return $message;
    }

    private function getSpeciesInfoForMessage(array $observation, int $countPlace, int $countWorld) : string
    {
        $message = 'Esta especie ';
        if ($observation['native']) {
            $message .= 'es nativa de Uruguay';
        } elseif ($observation['introduced']) {
            $message .= 'fue introducida en Uruguay';
        }

        if ($observation['threatened']) {
            if ($observation['native'] || $observation['introduced']) {
                $message .= ', ';
            }

            $message .= 'está amenazada y ';
        } elseif ($observation['native'] || $observation['introduced']) {
            $message .= ' y ';
        }

        // Info del número de registros
        if ($countPlace === 1) {
            if ($observation['introduced']) {
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
                    $message .= ' y en el mundo!';
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

        return $message;
    }
}
