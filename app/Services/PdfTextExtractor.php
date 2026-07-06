<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PdfTextExtractor
{
    public function extract(string $path, ?string $password = null): array
    {
        if ($password !== null && $password !== '') {
            return [
                'text' => $this->extractWithPython($path, $password),
                'password_required' => false,
            ];
        }

        try {
            $pdf = (new Parser())->parseFile($path);

            return [
                'text' => $pdf->getText(),
                'password_required' => false,
            ];
        } catch (Throwable $exception) {
            if ($this->isPasswordProtected($path, $exception)) {
                return [
                    'text' => null,
                    'password_required' => true,
                    'error' => 'Este PDF esta protegido por senha.',
                ];
            }

            throw $exception;
        }
    }

    private function extractWithPython(string $path, string $password): string
    {
        $python = env('PDF_PYTHON_PATH', 'C:\\Users\\lucas\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\python.exe');
        $script = base_path('scripts/extract_pdf_text.py');
        $process = new Process([$python, $script, $path, $password]);
        $process->setTimeout(30);

        try {
            $process->run();
        } catch (ProcessRuntimeException) {
            throw new RuntimeException('Nao foi possivel executar o leitor de PDF com senha. Verifique a configuracao PDF_PYTHON_PATH.');
        }

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: 'Nao foi possivel abrir o PDF com essa senha.';
            throw new RuntimeException($this->friendlyPythonError($error));
        }

        $text = trim($process->getOutput());

        if ($text === '') {
            throw new RuntimeException('A senha foi aceita, mas nenhum texto foi extraido do PDF.');
        }

        return $text;
    }

    private function friendlyPythonError(string $error): string
    {
        $lower = strtolower($error);

        if (str_contains($lower, 'senha invalida') || str_contains($lower, 'password')) {
            return 'Senha invalida para este PDF.';
        }

        if (str_contains($lower, 'nenhum texto')) {
            return 'A senha foi aceita, mas nenhum texto foi extraido do PDF.';
        }

        return $error;
    }

    private function isPasswordProtected(string $path, Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'secured') || str_contains($message, 'encrypt')) {
            return true;
        }

        $handle = @fopen($path, 'rb');

        if (! $handle) {
            return false;
        }

        $chunk = fread($handle, 1048576) ?: '';
        fclose($handle);

        return str_contains($chunk, '/Encrypt') || str_contains($chunk, '/encrypt');
    }
}
