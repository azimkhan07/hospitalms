<?php

namespace App\Http\Livewire;

use Livewire\Component;

class Search extends Component
{
    public $item;

    public function search()
    {
        $this->redirect(route('site.doctors'));
    }
    public function render()
    {
        return view('livewire.search');
    }
}
