<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Operations\Models\QueueTicket;
use App\Modules\Operations\Services\QueueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Antrean walk-in: ambil nomor, panggil, layani, selesai. Ada juga tampilan layar TV. */
class QueueController extends Controller
{
    public function __construct(
        private QueueService $queue,
        private CurrentOutlet $outlet,
        private TenantContext $context,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Operations/Queue', [
            ...$this->state($request),
            'staff' => User::sameTenant()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'display' => false,
        ]);
    }

    /** Layar besar untuk TV / tablet yang dilihat pelanggan. */
    public function display(Request $request): Response
    {
        return Inertia::render('Operations/QueueDisplay', $this->state($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['customer_name' => ['nullable', 'string', 'max:100'], 'service_note' => ['nullable', 'string', 'max:255']]);
        $ticket = $this->queue->take($this->outlet->id($request->user()), $data['customer_name'] ?? null, $data['service_note'] ?? null, $this->context->get()->timezone);

        return back()->with('success', "Nomor antrean {$ticket->number} sudah dibuat.");
    }

    public function next(Request $request): RedirectResponse
    {
        $ticket = $this->queue->callNext($this->outlet->id($request->user()), $this->context->get()->timezone);

        return back()->with('success', "Memanggil nomor {$ticket->number}".($ticket->customer_name ? " ({$ticket->customer_name})" : '').'.');
    }

    public function update(Request $request, QueueTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['waiting', 'called', 'serving', 'done', 'skipped'])],
            'staff_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $this->context->id())],
        ]);
        $this->queue->update($ticket, $data['status'], $data['staff_id'] ?? null);

        return back();
    }

    private function state(Request $request): array
    {
        $timezone = $this->context->get()->timezone;
        $tickets = QueueTicket::query()
            ->where('outlet_id', $this->outlet->id($request->user()))
            ->whereDate('queue_date', Carbon::now($timezone)->toDateString())
            ->with('staff:id,name')
            ->orderBy('number')
            ->get()
            ->map(fn (QueueTicket $t) => [
                'id' => $t->id, 'number' => $t->number, 'customer_name' => $t->customer_name, 'service_note' => $t->service_note,
                'status' => $t->status, 'staff' => $t->staff?->name, 'staff_id' => $t->staff_id,
            ]);

        return [
            'tickets' => $tickets,
            'current' => $tickets->whereIn('status', ['called', 'serving'])->sortByDesc('id')->values(),
            'waitingCount' => $tickets->where('status', 'waiting')->count(),
            'businessName' => $this->context->get()->name,
        ];
    }
}
