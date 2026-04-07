<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\Loans;
use App\Models\SmsTemplate;
use App\Models\SubShop;
use App\Services\Sms\SmsTemplateEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReminderSmsController extends Controller
{
    protected SmsTemplateEngine $templateEngine;

    public function __construct(SmsTemplateEngine $templateEngine)
    {
        $this->templateEngine = $templateEngine;
    }

    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        if (! $subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $templates = SmsTemplate::where('shop_id', $shopId)
            ->active()
            ->get();

        return view('sms.reminder_sms.index', compact('templates'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'reminder_type' => 'required|in:upcoming,overdue',
            'days' => 'required|integer|min:1|max:365',
            'template_id' => 'required|exists:sms_templates,id',
        ]);

        $subshopId = session('subshop_id');
        if (! $subshopId) {
            return response()->json(['error' => 'No subshop selected'], 403);
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $template = SmsTemplate::where('shop_id', $shopId)
            ->where('id', $request->template_id)
            ->firstOrFail();

        $loans = $this->getFilteredLoans($request->reminder_type, (int) $request->days, $shopId, $subshopId);

        Log::info('Reminder SMS Preview Debug', [
            'reminder_type' => $request->reminder_type,
            'days' => $request->days,
            'shop_id' => $shopId,
            'subshop_id' => $subshopId,
            'today' => now()->toDateString(),
            'target_date' => $request->reminder_type === 'upcoming'
                ? now()->addDays((int) $request->days)->toDateString()
                : now()->subDays((int) $request->days)->toDateString(),
            'total_loans_found' => $loans->count(),
            'loan_ids' => $loans->pluck('id')->toArray(),
        ]);

        if ($loans->isEmpty()) {
            return response()->json([
                'preview' => 'No loans found matching the criteria.',
                'count' => 0,
                'has_variables' => false,
                'debug' => [
                    'reminder_type' => $request->reminder_type,
                    'days' => $request->days,
                    'today' => now()->toDateString(),
                    'target_date' => $request->reminder_type === 'upcoming'
                        ? now()->addDays((int) $request->days)->toDateString()
                        : now()->subDays((int) $request->days)->toDateString(),
                ],
            ]);
        }

        $sampleLoan = $loans->first();

        $sampleData = $this->prepareSampleData($sampleLoan, $request->reminder_type, $request->days);
        $previewMessage = $this->templateEngine->render($template->message_template, $sampleData);

        $hasVariables = ! empty($template->variables) && is_array($template->variables);

        Log::info('Reminder SMS Sample Data', [
            'sample_loan_id' => $sampleLoan->id,
            'loan_code' => $sampleLoan->loan_code,
            'customer_name' => $sampleLoan->customer?->name,
            'sample_data' => $sampleData,
        ]);

        return response()->json([
            'preview' => $previewMessage,
            'count' => $loans->count(),
            'has_variables' => $hasVariables,
            'variables' => $template->variables ?? [],
            'debug' => [
                'sample_loan_id' => $sampleLoan->id,
                'loan_code' => $sampleLoan->loan_code,
                'customer_name' => $sampleLoan->customer?->name,
            ],
        ]);
    }

    public function send(Request $request)
    {
        $request->validate([
            'reminder_type' => 'required|in:upcoming,overdue',
            'days' => 'required|integer|min:1|max:365',
            'template_id' => 'required|exists:sms_templates,id',
        ]);

        $subshopId = session('subshop_id');
        if (! $subshopId) {
            return back()->with('error', 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        $userId = Auth::id();

        $template = SmsTemplate::where('shop_id', $shopId)
            ->where('id', $request->template_id)
            ->firstOrFail();

        $loans = $this->getFilteredLoans($request->reminder_type, (int) $request->days, $shopId, $subshopId);

        Log::info('Reminder SMS Send Debug', [
            'reminder_type' => $request->reminder_type,
            'days' => $request->days,
            'shop_id' => $shopId,
            'subshop_id' => $subshopId,
            'total_loans_found' => $loans->count(),
        ]);

        if ($loans->isEmpty()) {
            return back()->with('error', 'No loans found matching the criteria.');
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($loans as $loan) {
            $customer = $loan->customer;

            if (! $customer || ! $customer->phone) {
                Log::warning('Reminder SMS skipped - no customer or phone', [
                    'loan_id' => $loan->id,
                    'loan_code' => $loan->loan_code,
                    'customer_exists' => ! empty($customer),
                    'phone' => $customer?->phone,
                ]);
                $failedCount++;

                continue;
            }

            $data = $this->prepareSampleData($loan, $request->reminder_type, $request->days);
            $message = $this->templateEngine->render($template->message_template, $data);

            $payload = [
                'shop_id' => $shopId,
                'subshop_id' => $subshopId,
                'user_id' => $userId,
                'phone' => $customer->phone,
                'message' => $message,
                'data' => $data,
                'event' => 'loan.reminder.'.$request->reminder_type,
                'sensitive' => false,
            ];

            if($request->reminder_type === 'upcoming') {
                $eventName = 'loan.reminder.upcoming';
            }elseif($request->reminder_type === 'overdue') {
                $eventName = 'loan.reminder.overdue';
            }

            Log::info('Sending reminder SMS', [
                'loan_id' => $loan->id,
                'loan_code' => $loan->loan_code,
                'customer_name' => $customer->name,
                'phone' => $customer->phone,
                'message' => $message,
            ]);

            $result = app(\App\Services\Sms\SmsManager::class)->sendEvent($eventName, $payload);

            if ($result) {
                $sentCount++;
            } else {
                $failedCount++;
                Log::error('Failed to send reminder SMS', [
                    'loan_id' => $loan->id,
                    'loan_code' => $loan->loan_code,
                    'phone' => $customer->phone,
                ]);
            }
        }

        $resultMessage = "SMS reminders queued: {$sentCount} sent";
        if ($failedCount > 0) {
            $resultMessage .= ", {$failedCount} failed";
        }

        Log::info('Reminder SMS Send Complete', [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);

        return redirect()->route('sms.reminders.index')->with('success', $resultMessage);
    }

    protected function getFilteredLoans($reminderType, $days, $shopId, $subshopId)
    {
        $today = now()->toDateString();
        $days = (int) $days;

        if ($reminderType === 'upcoming') {
            $targetDate = now()->addDays($days)->toDateString();

            Log::debug('Upcoming Due Filter', [
                'today' => $today,
                'target_date' => $targetDate,
                'shop_id' => $shopId,
                'subshop_id' => $subshopId,
                'loan_statuses' => ['disbursed', 'partially_paid'],
                'installment_statuses' => ['pending', 'partial'],
            ]);

            return Loans::whereHas('subshop', function ($query) use ($shopId) {
                $query->where('shop_id', $shopId);
            })
                ->whereIn('status', ['disbursed', 'partially_paid'])
                ->where('is_active', true)
                ->whereHas('installments', function ($query) use ($targetDate, $today) {
                    $query->where('is_active', true)
                        ->whereIn('status', ['pending', 'partial'])
                        ->where('due_date', '>=', $today)
                        ->where('due_date', '<=', $targetDate);
                })
                ->with(['customer', 'installments' => function ($query) use ($targetDate, $today) {
                    $query->where('is_active', true)
                        ->whereIn('status', ['pending', 'partial'])
                        ->where('due_date', '>=', $today)
                        ->where('due_date', '<=', $targetDate)
                        ->orderBy('due_date')
                        ->limit(1);
                }])
                ->get();
        } else {
            $targetDate = now()->subDays($days)->toDateString();

            Log::debug('Overdue Filter', [
                'today' => $today,
                'target_date' => $targetDate,
                'shop_id' => $shopId,
                'subshop_id' => $subshopId,
                'loan_statuses' => ['disbursed', 'partially_paid'],
                'installment_statuses' => ['overdue'],
            ]);

            return Loans::whereHas('subshop', function ($query) use ($shopId) {
                $query->where('shop_id', $shopId);
            })
                ->whereIn('status', ['disbursed', 'partially_paid'])
                ->where('is_active', true)
                ->whereHas('installments', function ($query) use ($targetDate) {
                    $query->where('is_active', true)
                        ->where('status', 'overdue')
                        ->where('due_date', '<=', $targetDate);
                })
                ->with(['customer', 'installments' => function ($query) use ($targetDate) {
                    $query->where('is_active', true)
                        ->where('status', 'overdue')
                        ->where('due_date', '<=', $targetDate)
                        ->orderBy('due_date')
                        ->limit(1);
                }])
                ->get();
        }
    }

    protected function prepareSampleData($loan, $reminderType, $days)
    {
        $customer = $loan->customer;

        $today = now()->startOfDay();

        $nextInstallment = null;

        if ($reminderType === 'upcoming') {
            $targetDate = now()->addDays((int) $days)->startOfDay();

            $nextInstallment = $loan->installments()
                ->where('is_active', true)
                ->whereIn('status', ['pending', 'partial'])
                ->where('due_date', '>=', $today)
                ->where('due_date', '<=', $targetDate)
                ->orderBy('due_date')
                ->first();
        } else {
            $targetDate = now()->subDays((int) $days)->startOfDay();

            $nextInstallment = $loan->installments()
                ->where('is_active', true)
                ->where('status', 'overdue')
                ->where('due_date', '<=', $targetDate)
                ->orderBy('due_date')
                ->first();
        }

        $dueDateStr = $nextInstallment ? $nextInstallment->due_date->format('d-m-Y') : 'N/A';
        $dueAmount = $nextInstallment ? number_format($nextInstallment->total_due, 2) : '0.00';
        $outstanding = number_format($loan->outstanding_balance, 2);

        $overdueDays = 0;
        $daysUntilDue = 0;

        if ($nextInstallment) {
            $installmentDueDate = $nextInstallment->due_date->startOfDay();

            if ($installmentDueDate->isPast()) {
                $overdueDays = (int) $installmentDueDate->diffInDays($today);
            } else {
                $daysUntilDue = (int) $today->diffInDays($installmentDueDate);
            }
        }

        $data = [
            'name' => $customer?->name ?? 'Customer',
            'loan_code' => $loan->loan_code,
            'date' => $dueDateStr,
            'amount' => $dueAmount,
            'outstanding_balance' => $outstanding,
            'principal_amount' => number_format($loan->principal_amount, 2),
            'overdue_days' => $overdueDays,
            'days_until_due' => $daysUntilDue,
        ];

        return $data;
    }
}
