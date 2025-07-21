<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

use Illuminate\Queue\SerializesModels;

class VerificaCorreo extends Mailable //implements ShouldQueue
{
    use Queueable, SerializesModels;

    private $codigo;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($numeroAleatorio)
    {
        $this->codigo = $numeroAleatorio;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.codigo-email')
            ->subject('Código de verificación')
            ->with([
                'codigo' => $this->codigo
            ]);
    }
}
