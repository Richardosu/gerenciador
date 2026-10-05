<x-filament-panels::page>
    <div class="flex flex-wrap gap-2">
        <x-filament::button size="sm" :color="$activeTab === 'all' ? 'primary' : 'gray'" wire:click="setActiveTab('all')">
            Todas
        </x-filament::button>
        <x-filament::button size="sm" :color="$activeTab === 'todo' ? 'primary' : 'gray'" wire:click="setActiveTab('todo')">
            A fazer
        </x-filament::button>
        <x-filament::button size="sm" :color="$activeTab === 'in_progress' ? 'primary' : 'gray'" wire:click="setActiveTab('in_progress')">
            Em andamento
        </x-filament::button>
        <x-filament::button size="sm" :color="$activeTab === 'in_review' ? 'primary' : 'gray'" wire:click="setActiveTab('in_review')">
            Em revisão
        </x-filament::button>
        <x-filament::button size="sm" :color="$activeTab === 'overdue' ? 'primary' : 'gray'" wire:click="setActiveTab('overdue')">
            Atrasadas
        </x-filament::button>
        <x-filament::button size="sm" :color="$activeTab === 'completed' ? 'primary' : 'gray'" wire:click="setActiveTab('completed')">
            Concluídas
        </x-filament::button>
    </div>

    {{ $this->table }}
</x-filament-panels::page>