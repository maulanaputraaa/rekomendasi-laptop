<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\ReviewDataImport;
use Maatwebsite\Excel\Facades\Excel;

class ReviewImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);
        $import = new ReviewDataImport();
        Excel::import($import, $request->file('file'));
        $message = "Data berhasil diimpor ({$import->totalData} data diproses)";
        if ($import->duplicates > 0) {
            $message .= ". {$import->duplicates} data duplikat tidak diimpor";
        }
        return back()->with('success', $message);
    }
}
