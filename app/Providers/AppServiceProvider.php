<?php

namespace App\Providers;

use App\Models\Tuntutan;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $user = Auth::user();
            $notifications = [
                'awaiting_review' => false,
                'awaiting_receipt_review' => false,
                'awaiting_receipt_upload' => false,
            ];

            if ($user !== null && $user->hasRole('Superadmin')) {
                $notifications['awaiting_review'] = Tuntutan::query()
                    ->awaitingReview()
                    ->exists();
                $notifications['awaiting_receipt_review'] = Tuntutan::query()
                    ->awaitingReceiptReview()
                    ->exists();
            }

            if ($user !== null && $user->hasRole('Stocker')) {
                $notifications['awaiting_receipt_upload'] = Tuntutan::query()
                    ->where('user_id', $user->id)
                    ->awaitingReceiptUpload()
                    ->exists();
            }

            $view->with('purchaseRequestNotifications', $notifications);
        });
    }
}
