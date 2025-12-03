<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Jobs\PrintEscposJob;
use App\Models\SubShop;

class PrintJobsController extends Controller
{
    public function index(Request $request)
    {
        $jobs = Cache::get('print_jobs:index', []);
        $subshop = null;
        $sid = (int) $request->session()->get('subshop_id');
        if ($sid) { $subshop = SubShop::find($sid); }
        return view('printers.print_jobs', ['jobs' => $jobs, 'subshop' => $subshop]);
    }

    public function status(Request $request)
    {
        $jobId = $request->input('job_id');
        if (!$jobId) {
            return response()->json(['ok' => false, 'error' => 'job_id is required'], 422);
        }
        $data = Cache::get('print_job:' . $jobId);
        if (!$data) {
            return response()->json(['ok' => true, 'status' => 'unknown']);
        }
        return response()->json(array_merge(['ok' => true], $data));
    }

    public function retry(Request $request)
    {
        $jobId = $request->input('job_id');
        if (!$jobId) {
            return response()->json(['ok' => false, 'error' => 'job_id is required'], 422);
        }
        $data = Cache::get('print_job:' . $jobId);
        if (!$data) {
            return response()->json(['ok' => false, 'error' => 'Job not found'], 404);
        }
        // Only allow retry if failed or unknown
        $status = strtolower($data['status'] ?? 'unknown');
        if (!in_array($status, ['failed', 'unknown'])) {
            return response()->json(['ok' => false, 'error' => 'Job is not failed'], 422);
        }
        $docType = $data['docType'] ?? null;
        $docId = $data['docId'] ?? null;
        $printerSettingId = $data['printerSettingId'] ?? null;
        if (!$docType || !$docId || !$printerSettingId) {
            return response()->json(['ok' => false, 'error' => 'Missing job metadata'], 422);
        }
        $newJob = new PrintEscposJob((string)$docType, (int)$docId, (int)$printerSettingId);
        $newId = $newJob->jobId;
        dispatch($newJob);
        return response()->json(['ok' => true, 'job_id' => $newId]);
    }
}
