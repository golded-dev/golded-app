<?php

use App\Golded\GoldedState;
use App\Golded\HtmlRenderer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::terminal')] #[Title('GoldED 7')] class extends Component
{
    public string $screen = 'areas';
    public ?int $areaId = null;
    public ?int $messageId = null;
    public int $selectionIndex = 0;
    public int $scrollOffset = 0;
    public int $topOffset = 0;
    public bool $showKludges = false;

    #[Computed]
    public function areas(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->state->areas();
    }

    #[Computed]
    public function state(): GoldedState
    {
        $state                 = new GoldedState;
        $state->screen         = $this->screen;
        $state->areaId         = $this->areaId;
        $state->messageId      = $this->messageId;
        $state->selectionIndex = $this->selectionIndex;
        $state->scrollOffset   = $this->scrollOffset;
        $state->topOffset      = $this->topOffset;
        $state->showKludges    = $this->showKludges;

        return $state;
    }

    public function handleKey(string $key): void
    {
        $state = new GoldedState;
        $state->screen         = $this->screen;
        $state->areaId         = $this->areaId;
        $state->messageId      = $this->messageId;
        $state->selectionIndex = $this->selectionIndex;
        $state->scrollOffset   = $this->scrollOffset;
        $state->topOffset      = $this->topOffset;
        $state->showKludges    = $this->showKludges;

        $state->handleKey($key);

        $this->screen         = $state->screen;
        $this->areaId         = $state->areaId;
        $this->messageId      = $state->messageId;
        $this->selectionIndex = $state->selectionIndex;
        $this->scrollOffset   = $state->scrollOffset;
        $this->topOffset      = $state->topOffset;
        $this->showKludges    = $state->showKludges;

        unset($this->state);
    }

    /** @return array<int, string> 25 HTML line strings */
    public function currentScreen(): array
    {
        return (new HtmlRenderer)->renderScreen(
            $this->state->currentScreen(),
            80
        );
    }
}
?>

<div
    x-data
    @keydown.window="$wire.handleKey(($event.altKey ? 'Alt+' : '') + $event.key)"
    class="golded-shell"
    tabindex="-1"
>
    <pre class="golded-pre">@foreach ($this->currentScreen() as $i => $line)<span class="{{ $i === 24 ? 'golded-row-status' : 'golded-row' }}">{!! $line !!}</span>@endforeach</pre>

</div>
