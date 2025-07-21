<?php

use App\Enums\Auth\EstatusVerificacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCorreoVerificacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('correo_verificacion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_correo', 50)->nullable();
            $table->string('correo', 255)->nullable();
            $table->enum('estatus_verificacion', EstatusVerificacion::getEstatusVerificacion())
                ->default(EstatusVerificacion::PENDIENTE);
            $table->timestamp('fecha_creacion');
            $table->timestamp('fecha_verificacion')->nullable();
            $table->integer('conteo')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('correo_verificacion');
    }
}
