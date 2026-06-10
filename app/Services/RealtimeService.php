<?php

namespace App\Services;

use App\Events\RealtimeEvent;
use App\Enum\RealtimeModule;
use App\Enum\RealtimeAction;

class RealtimeService
{
    public function emit(
        RealtimeModule $module,
        RealtimeAction $action,
        array $data = []
    ): void {
        broadcast(
            new RealtimeEvent(
                module: $module->value,
                action: $action->value,
                data: $data
            )
        );
    }

    public function created(RealtimeModule $module, array $data = []): void
    {
        $this->emit($module, RealtimeAction::CREATED, $data);
    }

    public function updated(RealtimeModule $module, array $data = []): void
    {
        $this->emit($module, RealtimeAction::UPDATED, $data);
    }

    public function deleted(RealtimeModule $module, array $data = []): void
    {
        $this->emit($module, RealtimeAction::DELETED, $data);
    }

    public function completed(RealtimeModule $module, array $data = []): void
    {
        $this->emit($module, RealtimeAction::COMPLETED, $data);
    }

    public function approved(RealtimeModule $module, array $data = []): void
    {
        $this->emit($module, RealtimeAction::APPROVED, $data);
    }

    public function rejected(RealtimeModule $module, array $data = []): void
    {
        $this->emit($module, RealtimeAction::REJECTED, $data);
    }

    public function refresh(RealtimeModule $module, array $data = []): void
    {
        $this->emit($module, RealtimeAction::REFRESH, $data);
    }

    public function notify(array $data = []): void
    {
        $this->emit(
            RealtimeModule::DASHBOARD,
            RealtimeAction::NOTIFICATION,
            $data
        );
    }
}