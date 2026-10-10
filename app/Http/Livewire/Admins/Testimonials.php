<?php

namespace App\Http\Livewire\Admins;

use App\Models\testimonial;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic;
use Illuminate\Support\Str;

#[Layout('admins.layouts.app')]
class Testimonials extends Component
{
    use WithFileUploads;

    public $name = '';
    public $role = '';
    public $quote = '';
    public $sort_order = 0;
    public $active = true;
    public $photo;
    public $edit_id = null;
    public $_page = 'index';

    public function mount(): void
    {
        $this->_page = 'index';
    }

    public function show_create_form()
    {
        $this->resetInput();
        $this->_page = 'create';
    }

    public function edit($id)
    {
        $item = testimonial::findOrFail($id);
        $this->edit_id = $id;
        $this->name = $item->name;
        $this->role = $item->role;
        $this->quote = $item->quote;
        $this->sort_order = $item->sort_order;
        $this->active = (bool) $item->active;
        $this->_page = 'edit';
    }

    public function cancel()
    {
        $this->resetInput();
        $this->_page = 'index';
    }

    public function store()
    {
        $this->validate([
            'name' => 'required|max:120',
            'role' => 'nullable|max:120',
            'quote' => 'required|max:400',
            'sort_order' => 'required|integer|min:0',
            'active' => 'required|boolean',
            'photo' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:2048',
        ]);

        $attributes = [
            'name' => $this->name,
            'role' => $this->role,
            'quote' => $this->quote,
            'sort_order' => (int) $this->sort_order,
            'active' => (bool) $this->active,
        ];

        if ($this->photo) {
            $attributes['photo'] = $this->storeImage();
        }

        if ($this->edit_id) {
            $item = testimonial::findOrFail($this->edit_id);
            if ($this->photo && $item->photo) {
                Storage::disk('public')->delete($item->photo);
            }
            $item->update($attributes);
            session()->flash('message', 'Testimonial updated successfully.');
        } else {
            testimonial::create($attributes);
            session()->flash('message', 'Testimonial created successfully.');
        }

        $this->resetInput();
        $this->_page = 'index';
    }

    public function toggle($id)
    {
        $item = testimonial::findOrFail($id);
        $item->update(['active' => ! $item->active]);
    }

    public function delete($id)
    {
        $item = testimonial::findOrFail($id);
        if ($item->photo) {
            Storage::disk('public')->delete($item->photo);
        }
        $item->delete();
        session()->flash('message', 'Testimonial deleted successfully.');
    }

    protected function storeImage()
    {
        $img = ImageManagerStatic::make($this->photo)->encode('jpg');
        $img->resize(140, 140);
        $name = Str::random() . '.jpg';

        Storage::disk('public')->put($name, $img);

        return $name;
    }

    protected function resetInput(): void
    {
        $this->name = '';
        $this->role = '';
        $this->quote = '';
        $this->sort_order = 0;
        $this->active = true;
        $this->photo = null;
        $this->edit_id = null;
    }

    public function render()
    {
        if (! hms_can('settings')) {
            abort(403);
        }

        return view('livewire.admins.testimonials', [
            'testimonials' => testimonial::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}