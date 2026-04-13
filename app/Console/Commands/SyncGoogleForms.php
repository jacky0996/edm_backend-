<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Google\GoogleForm;
use App\Models\Google\GoogleFormResponse;
use App\Models\Google\GoogleFormStat;
use App\Services\GoogleApiService;

class SyncGoogleForms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-google-forms {id? : GoogleForm DB ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Google Form responses to database and update stats';

    /**
     * Execute the console command.
     */
    public function handle(GoogleApiService $googleApi)
    {
        $id = $this->argument('id');

        if ($id) {
            $forms = GoogleForm::where('id', $id)->get();
        } else {
            $forms = GoogleForm::all();
        }

        if ($forms->isEmpty()) {
            $this->error('No Google Forms found.');
            return;
        }

        foreach ($forms as $googleForm) {
            $this->info("Processing Form ID: {$googleForm->id} ({$googleForm->form_id})");

            // 1. Sync Responses
            $result = $googleApi->syncFormFills($googleForm->id);
            if ($result['status'] === true) {
                $this->info("Successfully synced {$result['synced_count']} responses.");
            } else {
                $this->error("Failed to sync Form ID {$googleForm->id}: " . ($result['error'] ?? 'Unknown error'));
            }
        }


        $this->info('Completed sync processing.');
    }
}
