<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SignedStorageController extends Controller
{
    /**
     * Serve a file from the local disk; reachable only through a signed_storage_url() link.
     */
    public function show(string $file): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_if(str_contains($file, '..') || ! $disk->exists($file), 404);

        return $disk->response($file);
    }
}
