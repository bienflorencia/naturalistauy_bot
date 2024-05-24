<?php

namespace App\Console\Commands;

use App\Services\Naturalist;
use App\Services\Tacuruses;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TopIdentifiers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iNat:top-identifiers {taxonId} {--from=} {--to=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(Tacuruses $fediApi, Naturalist $natuApi)
    {
        $from = Carbon::parse($this->option('from'));
        $to = Carbon::parse($this->option('to'));
        $taxonId = $this->argument('taxonId');

        $top = $natuApi->getTopIdentifiers(
            from: $from->format('Y-m-d'),
            to: $to->format('Y-m-d'),
            taxonId: $taxonId
        );

        $taxaName = $natuApi->getNameForIconicTaxaId($taxonId);
        $commonName = $natuApi->getCommonNameForIconicTaxa($taxaName);
        $emoji = $natuApi->getEmojiForIconicTaxa($taxaName);

        $message = "<p>Top identificadores de la última semana para <b>$commonName</b> $emoji";
        $message .= ":</p><ol>";

        $medals = ['🥇', '🥈', '🥉', '', ''];

        $top->take(5)->each(function(array $user, $index) use (&$message, $medals) {
            $message .= "<li>{$medals[$index]}";
            if (!empty($user['user']['name'])) {
                $message .= "{$user['user']['name']} <a href=\"https://www.naturalista.uy/people/{$user['user']['login']}\">({$user['user']['login']})";
            } else {
                $message .= "<a href=\"https://www.naturalista.uy/people/{$user['user']['login']}\">" . $user['user']['login'];
            }
            $message .= "</a> {$user['count']} identificaciones.</li>";
        });

        $message .= "</ol>";

        if ($this->option('dry-run')) {
            $this->info(str_replace('<br>', PHP_EOL, $message));
        } else {
            $fediApi->publishPost($message);
        }
    }
}
