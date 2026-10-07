<?php

namespace App\Modules\Staff\Http\Resources;

use App\Core\Support\Phone;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role()?->value,
            'role_label' => $this->roleLabel(),
            'job_title' => $this->job_title,
            'phone' => Phone::display($this->phone),
            'has_pin' => $this->hasPin(),
            'has_password' => $this->password !== null,
            'is_active' => $this->is_active,
            'is_self' => $viewer?->id === $this->id,
            'outlet_ids' => $this->whenLoaded('outlets', fn () => $this->outlets->pluck('id')),
            'outlet_names' => $this->whenLoaded('outlets', fn () => $this->outlets->pluck('name')),
            'can' => [
                'update' => $viewer?->can('update', $this->resource) ?? false,
                'delete' => $viewer?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
