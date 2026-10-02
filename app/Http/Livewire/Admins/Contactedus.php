<?php

namespace App\Http\Livewire\Admins;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\contact;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class Contactedus extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';
    public function delete($id)
    {
        contact::findOrFail($id)->delete();
        session()->flash('message', 'Message Deleted Successfully.');

}
    public function render()
    {
        if (! hms_can('messages')) { abort(403); }

        return view('livewire.admins.contactedus',[
            'contacted' => contact::latest()->paginate(5),
        ]);
    }
}
