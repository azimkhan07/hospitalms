<?php

namespace App\Http\Livewire\Admins;

use App\Models\feature;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic;
use Illuminate\Support\Str;

#[Layout('admins.layouts.app')]
class Features extends Component
{
    use WithFileUploads;

    public $title = '';
    public $text = '';
    public $sort_order = 0;
    public $active = true;
    public $icon;
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
        $feature = feature::findOrFail($id);
        $this->edit_id = $id;
        $this->title = $feature->title;
        $this->text = $feature->text;
        $this->sort_order = $feature->sort_order;
        $this->active = (bool) $feature->active;
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
            'title' => 'required|max:120',
            'text' => 'required|max:255',
            'sort_order' => 'required|integer|min:0',
            'active' => 'required|boolean',
            'icon' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:2048',
        ]);

        $attributes = [
            'title' => $this->title,
            'text' => $this->text,
            'sort_order' => (int) $this->sort_order,
            'active' => (bool) $this->active,
        ];

        if ($this->icon) {
            $attributes['icon'] = $this->storeImage();
        }

        if ($this->edit_id) {
            $feature = feature::findOrFail($this->edit_id);
            if ($this->icon && $feature->icon) {
                Storage::disk('public')->delete($feature->icon);
            }
            $feature->update($attributes);
            session()->flash('message', 'Feature updated successfully.');
        } else {
            feature::create($attributes);
            session()->flash('message', 'Feature created successfully.');
        }

        $this->resetInput();
        $this->_page = 'index';
    }

    public function toggle($id)
    {
        $feature = feature::findOrFail($id);
        $feature->update(['active' => ! $feature->active]);
    }

    public function delete($id)
    {
        $feature = feature::findOrFail($id);
        if ($feature->icon) {
            Storage::disk('public')->delete($feature->icon);
        }
        $feature->delete();
        session()->flash('message', 'Feature deleted successfully.');
    }

    protected function storeImage()
    {
        $img = ImageManagerStatic::make($this->icon)->encode('jpg');
        $img->resize(140, 140);
        $name = Str::random() . '.jpg';

        Storage::disk('public')->put($name, $img);

        return $name;
    }

    protected function resetInput(): void
    {
        $this->title = '';
        $this->text = '';
        $this->sort_order = 0;
        $this->active = true;
        $this->icon = null;
        $this->edit_id = null;
    }

    public function render()
    {
        if (! hms_can('settings')) {
            abort(403);
        }

        return view('livewire.admins.features', [
            'features' => feature::orderBy('sort_order')->orderBy('title')->get(),
        ]);
    }
}