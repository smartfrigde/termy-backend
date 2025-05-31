<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Doctrine\Common\Lexer\Token;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use function Pest\Laravel\delete;

class ClearDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tokens = PersonalAccessToken::where('expires_at', '<', Carbon::now())->get();
        $this->info("Downloading expires_at tokens");

        foreach ($tokens as $token) {
            $token->delete();
            $this->info("Deleted token with id {$token->id} ");
        }
    }
}
