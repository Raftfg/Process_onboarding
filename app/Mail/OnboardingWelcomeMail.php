<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OnboardingWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $adminData;
    public $subdomain;
    public $url;

    public function __construct(array $adminData, string $subdomain, string $url)
    {
        $this->adminData = $adminData;
        $this->subdomain = $subdomain;
        $this->url = $url;
    }

    public function build()
    {
        return $this->subject('Bienvenue sur MedKey - Votre compte est prêt !')
                    ->view('emails.onboarding-welcome')
                    ->with([
                        'adminName' => $this->adminData['admin_first_name'] . ' ' . $this->adminData['admin_last_name'],
                        'subdomain' => $this->subdomain,
                        'url' => $this->url,
                    ]);
    }
}
