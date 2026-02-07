<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $organizationName;
    public $activationToken;
    public $activationUrl;
    public $expiresInDays;

    public function __construct(string $email, string $organizationName, string $activationToken)
    {
        $this->email = $email;
        $this->organizationName = $organizationName;
        $this->activationToken = $activationToken;
        $this->expiresInDays = config('app.activation_token_expires_days', 7);
        
        // Construire l'URL d'activation
        // Utiliser route() pour générer l'URL proprement
        $this->activationUrl = route('onboarding.activation', [
            'token' => $activationToken,
            'email' => $email
        ]);
    }

    public function build()
    {
        return $this->subject(trans('emails.activation_subject', ['brand' => config('app.brand_name')]))
                    ->view('emails.activation')
                    ->with([
                        'email' => $this->email,
                        'organizationName' => $this->organizationName,
                        'activationUrl' => $this->activationUrl,
                        'expiresInDays' => $this->expiresInDays,
                    ]);
    }
}
