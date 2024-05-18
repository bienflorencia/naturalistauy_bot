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
        $observationsCount = $natuApi->getTaxonCount(
            $observationsOnDate->pluck('taxon_id')->toArray()
        )->map(fn (array $observation) => [
            'taxon_id' => Arr::get($observation, 'taxon.id'),
            'count_natUY' => Arr::get($observation, 'count'),
            'count_iNat' => Arr::get($observation, 'taxon.observations_count'),
        ])->sortBy('count_natUY');

        $mostRare = $observationsOnDate->firstWhere('taxon_id', '=', $observationsCount->first()['taxon_id']);

        $message = "<p><a href=\"{$mostRare['url']}\">Registro del día</a><br>";
        $message .= 'Esta fue la especie con menos observaciones registradas en Uruguay en el día de ayer:<br><br><b>';
        if (!empty($mostRare['common_name'])) {
            $message .= "{$mostRare['common_name']} ({$mostRare['taxon_name']}), ";
        } else {
            $message .= "{$mostRare['taxon_name']}, ";
        }
        $message .= $mostRare['iconic_taxa'] . '.</b><br><br>';
        $message .= '<blockquote>Observada por <a href="https://www.naturalista.uy/people/' . $mostRare['username'] . '">' . $mostRare['username'] . '</a> el ';
        $message .= $mostRare['observed_on']->locale('es_UY')->isoFormat('dddd D [de] MMMM [de] YYYY') . '.<br></blockquote>';
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

        // TODO: check language settings for plural localization
        $message .= 'ha sido registrada en Uruguay ' . $observationsCount->first()['count_natUY'];
        $message .= ' ' . Str::plural('vez', $observationsCount->first()['count_natUY']) . '.</p>';


        if ($this->option('dry-run')) {
            $this->info(str_replace('<br>', PHP_EOL, $message));
        } else {
            $fediApi->publishPost($message);
        }

    }
}
