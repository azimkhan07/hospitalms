<?php

namespace App\Http\Livewire\Admins;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\subscriber;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class Subscibers extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

     public function delete($id)
    {
        subscriber::findOrFail($id)->delete();
        session()->flash('message', 'Subscriber Deleted Successfully.');

}
    public function render()
    {
        if (! hms_can('subscribers')) { abort(403); }

        return view('livewire.admins.subscibers',[
            'subscribers' => subscriber::latest()->paginate(10)
        ]);
    }
}
