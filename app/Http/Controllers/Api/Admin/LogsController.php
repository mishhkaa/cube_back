<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogsController extends Controller
{
    public function index(): array
    {
        $filePath = $this->request->query('file');

        $files = File::files(storage_path('logs'));

        $data = [
            'file' => '',
            'files' => [],
            'content' => 'No found logs'
        ];

        foreach ($files as $index => $file){
            $fileName = $file->getFilename();
            $data['files'][] = ['value' => $fileName, 'label' => $fileName];
            if (!$index || $filePath == $fileName){
                $data['file'] = $fileName;
                $data['content'] = $file->getSize() > 2072576 ? 'File size is big': $file->getContents();
            }
        }

        return $data;
    }


    public function destroy(): JsonResponse
    {
        if (!$file = $this->request->query('file')){
            $this->response('query file path is required', false);
        }

        $path = storage_path("logs/$file");
        if (!File::exists($path)){
            $this->response('File not found', false);
        }

        return $this->response(success: File::delete($path));
    }
}
