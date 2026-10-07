<?php

namespace App\Modules\Operations\Services;

use App\Modules\Operations\Models\QueueTicket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Antrean walk-in (tempat cukur, salon): ambil nomor, panggil, layani, selesai. */
class QueueService
{
    public function take(int $outletId, ?string $name, ?string $note, string $timezone): QueueTicket
    {
        $today = Carbon::now($timezone)->toDateString();

        return DB::transaction(function () use ($outletId, $name, $note, $today) {
            $last = (int) QueueTicket::query()->where('outlet_id', $outletId)->whereDate('queue_date', $today)->lockForUpdate()->max('number');

            return QueueTicket::create([
                'outlet_id' => $outletId,
                'queue_date' => $today,
                'number' => $last + 1,
                'customer_name' => $name,
                'service_note' => $note,
                'status' => 'waiting',
            ]);
        });
    }

    public function callNext(int $outletId, string $timezone): QueueTicket
    {
        $ticket = QueueTicket::query()->where('outlet_id', $outletId)->whereDate('queue_date', Carbon::now($timezone)->toDateString())
            ->where('status', 'waiting')->orderBy('number')->first();

        if (! $ticket) {
            throw ValidationException::withMessages(['queue' => 'Tidak ada antrean yang menunggu.']);
        }

        $ticket->update(['status' => 'called', 'called_at' => now()]);

        return $ticket;
    }

    public function update(QueueTicket $ticket, string $status, ?int $staffId = null): QueueTicket
    {
        $ticket->update([
            'status' => $status,
            'staff_id' => $staffId ?? $ticket->staff_id,
            'called_at' => $status === 'called' ? now() : $ticket->called_at,
            'finished_at' => in_array($status, ['done', 'skipped'], true) ? now() : null,
        ]);

        return $ticket;
    }
}
