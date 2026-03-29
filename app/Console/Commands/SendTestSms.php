<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Sms\SmsManager;
use App\Models\SmsEvent;
use App\Models\SmsTemplate;
use App\Models\SmsConfig;

class SendTestSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-test-sms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test SMS using the event system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing SMS event system...');
        $this->newLine();

        // Check if required records exist
        $shopId = 1;
        $eventName = 'loan.disbursed';

        // Check SmsEvent
        $smsEvent = SmsEvent::where('shop_id', $shopId)
            ->where('event_name', $eventName)
            ->first();

        if (!$smsEvent) {
            $this->error('❌ SmsEvent record not found');
            $this->line("No SmsEvent exists for shop_id={$shopId} and event_name='{$eventName}'");
            $this->newLine();
            $this->info('To create the required records, run:');
            $this->line('php artisan tinker');
            $this->line('Then execute:');
            $this->line("use App\\Models\\SmsEvent;");
            $this->line("use App\\Models\\SmsTemplate;");
            $this->line("use App\\Models\\SmsConfig;");
            $this->newLine();
            $this->line("// Create a template first");
            $this->line("\$template = SmsTemplate::create([");
            $this->line("    'shop_id' => {$shopId},");
            $this->line("    'name' => 'Loan Disbursed',");
            $this->line("    'event' => '{$eventName}',");
            $this->line("    'message_template' => 'Hello {{name}}, your loan of {{amount}} has been disbursed. Reference: {{reference}}',");
            $this->line("    'variables' => ['name', 'amount', 'reference'],");
            $this->line("    'is_active' => true");
            $this->line("]);");
            $this->newLine();
            $this->line("// Create the event");
            $this->line("SmsEvent::create([");
            $this->line("    'shop_id' => {$shopId},");
            $this->line("    'event_name' => '{$eventName}',");
            $this->line("    'template_id' => \$template->id,");
            $this->line("    'is_enabled' => true");
            $this->line("]);");
            $this->newLine();
            $this->line("// Create SMS config (if not exists)");
            $this->line("SmsConfig::create([");
            $this->line("    'shop_id' => {$shopId},");
            $this->line("    'provider' => 'beem',");
            $this->line("    'api_url' => 'https://apisms.beem.africa/v1/send',");
            $this->line("    'api_key' => 'YOUR_API_KEY',");
            $this->line("    'secret_key' => 'YOUR_SECRET_KEY',");
            $this->line("    'sender_id' => 'DukaBase',");
            $this->line("    'is_active' => true,");
            $this->line("    'is_default' => true");
            $this->line("]);");

            return Command::FAILURE;
        }

        $this->info('✅ SmsEvent found: ID=' . $smsEvent->id);

        // Check if event is enabled
        if (!$smsEvent->is_enabled) {
            $this->error('❌ SmsEvent is disabled');
            $this->line("SmsEvent ID {$smsEvent->id} has is_enabled=false");
            return Command::FAILURE;
        }

        $this->info('✅ SmsEvent is enabled');

        // Check template
        $template = $smsEvent->template;
        if (!$template) {
            $this->error('❌ SmsTemplate not found');
            $this->line("SmsEvent ID {$smsEvent->id} has no template_id set");
            return Command::FAILURE;
        }

        $this->info('✅ SmsTemplate found: ID=' . $template->id . ', Name=' . $template->name);

        // Check if template is active
        if (!$template->is_active) {
            $this->error('❌ SmsTemplate is inactive');
            $this->line("SmsTemplate ID {$template->id} has is_active=false");
            return Command::FAILURE;
        }

        $this->info('✅ SmsTemplate is active');

        // Check SmsConfig
        $config = SmsConfig::where('shop_id', $shopId)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            $config = SmsConfig::where('shop_id', $shopId)
                ->where('is_default', true)
                ->first();
        }

        if (!$config) {
            $this->error('❌ SmsConfig not found');
            $this->line("No active or default SmsConfig exists for shop_id={$shopId}");
            return Command::FAILURE;
        }

        $this->info('✅ SmsConfig found: ID=' . $config->id . ', Provider=' . $config->provider);

        // All checks passed, send the SMS
        $this->newLine();
        $this->info('Sending SMS via event system...');

        $smsManager = app(SmsManager::class);

        $result = $smsManager->sendEvent($eventName, [
            'shop_id' => $shopId,
            'phone' => '0653709810',
            'user_id' => 1,
            'data' => [
                'name' => 'Test User',
                'amount' => '100,000 TZS',
                'reference' => 'TEST-001'
            ]
        ]);

        if ($result) {
            $this->info('✅ SMS queued successfully!');
            $this->line('Check the sms_logs table for the queued SMS record.');
            return Command::SUCCESS;
        } else {
            $this->error('❌ SMS failed to queue');
            $this->line('Check Laravel logs for detailed error messages.');
            return Command::FAILURE;
        }
    }
}
