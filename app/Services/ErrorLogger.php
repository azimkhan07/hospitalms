<?php

namespace App\Services;

use App\Models\SystemError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ErrorLogger
{
    /**
     * Exception types we never want to record as system errors
     * (normal, expected control-flow exceptions).
     *
     * @var array<int, class-string>
     */
    protected array $ignore = [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
        NotFoundHttpException::class,
        TokenMismatchException::class,
        HttpResponseException::class,
        ThrottleRequestsException::class,
    ];

    public function log(Throwable $e): void
    {
        if ($this->shouldIgnore($e)) {
            return;
        }

        try {
            if (! Schema::hasTable('system_errors')) {
                return;
            }

            SystemError::create($this->payload($e));
        } catch (Throwable) {
            // Never let the error monitor crash the app.
        }
    }

    protected function shouldIgnore(Throwable $e): bool
    {
        foreach ($this->ignore as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Throwable $e): array
    {
        $user = auth()->user();

        return [
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'role' => $user?->roleSlug(),
            'level' => $e instanceof QueryException ? 'critical' : 'error',
            'method' => $this->requestMethod(),
            'url' => $this->requestUrl(),
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $this->relativePath($e->getFile()),
            'line' => $e->getLine(),
            'stack' => mb_substr($e->getTraceAsString(), 0, 60000),
            'context' => $this->context(),
        ];
    }

    protected function requestMethod(): string
    {
        return app()->runningInConsole() ? 'CLI' : (string) request()->method();
    }

    protected function requestUrl(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return mb_substr((string) request()->fullUrl(), 0, 1000);
    }

    /**
     * @return array<string, mixed>
     */
    protected function context(): array
    {
        if (app()->runningInConsole()) {
            return [];
        }

        return [
            'input' => request()->except(['password', 'password_confirmation', '_token']),
            'ip' => request()->ip(),
            'agent' => mb_substr((string) request()->userAgent(), 0, 500),
        ];
    }

    protected function relativePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
