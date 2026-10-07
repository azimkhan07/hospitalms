<?php

namespace App\Http\Livewire\Admins;

use App\Models\patient;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class Patients extends Component
{
    use WithFileUploads;
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $name;
    public $email;
    public $phone;
    public $gender;
    public $age;
    public $address;
    public $bloodgroup;
    public $scheme;
    public $photo;
    public $edit_photo;
    public $edit_patient_id;
    public $button_text = "Add New Patient";

    public string $search = '';
    public string $schemeFilter = '';
    public string $angioFilter = '';

    public $_page;
    public function mount()
    {
        $this->_page = 'index';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->schemeFilter = '';
        $this->angioFilter = '';
        $this->resetPage();
    }

    public function show_create_form()
    {
        $this->_page = "create";
    }

    public function show_edit_form($id)
    {
        $this->_page = "edit";
        $patient = patient::findOrFail($id);
        $this->edit_patient_id = $id;

        $this->name = $patient->name;
        $this->email = $patient->email;
        $this->address = $patient->address;
        $this->phone = $patient->phone;
        $this->age = $patient->age;
        $this->bloodgroup = $patient->bloodgroup;
        $this->scheme = $patient->scheme_id;
        $this->edit_photo = $patient->photo_path;
    }

    public function show_index()
    {
        $this->_page = "index";
    }

    public function add_patient()
    {
        if ($this->edit_patient_id) {

            $this->update($this->edit_patient_id);

        } else {
            $this->validate([
                'name' => 'required||min:6|max:50',
                'email' => 'required|email',
                'address' => 'required',
                'phone' => 'required|numeric|max:10000000000000',
                'gender' => 'required',
                'age' => 'required',
                'bloodgroup' => 'nullable',
                'photo' => 'nullable|max:3072',
            ]);

            patient::create([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'gender' => $this->gender,
                'address' => $this->address,
                'age' => $this->age,
                'bloodgroup' => $this->bloodgroup,
                'scheme_id' => $this->scheme ?: null,
                'photo_path' => $this->storeImage(),
            ]);
            //unset variables
            $this->name = "";
            $this->email = "";
            $this->password = "";
            $this->address = "";
            $this->phone = "";
            $this->bloodgroup = "";
            $this->address = "";
            $this->age = "";
            $this->scheme = "";
            $this->photo = "";

            session()->flash('message', 'Patient Created successfully.');
            $this->_page = "index";
        }

    }

    public function storeImage()
    {
        if (!$this->photo) {
            return null;
        }
        $imag = ImageManagerStatic::make($this->photo)->encode('jpg');
        $name = Str::random() . '.jpg';
        Storage::disk('public')->put($name, $imag);
        return $name;
    }


    public function update($id)
    {
        $this->validate([
            'name' => 'required||min:6|max:50',
            'email' => 'required|email',
            'address' => 'required',
            'phone' => 'required|numeric|max:10000000000000',
            'gender' => 'required',
            'age' => 'required',
            'bloodgroup' => 'nullable',
            'photo' => 'nullable|max:3072',
        ]);

        $patient = patient::findOrFail($id);
        $patient->name = $this->name;
        $patient->email = $this->email;
        $patient->address = $this->address;
        $patient->age = $this->age;
        $patient->bloodgroup = $this->bloodgroup;
        $patient->phone = $this->phone;
        $patient->scheme_id = $this->scheme ?: null;

        if ($this->photo) {
            Storage::disk('public')->delete($patient->photo_path);
            $patient->photo_path = $this->storeImage();

        }

        $patient->save();

        $this->name = "";
        $this->email = "";
        $this->address = "";
        $this->phone = "";
        $this->bloodgroup = "";
        $this->address = "";
        $this->age = "";
        $this->scheme = "";
        $this->photo = "";
        $this->edit_photo = "";
        $this->edit_patient_id = "";

        session()->flash('message', 'Patient Updated Successfully.');

        $this->button_text = "Add New Patient";
        $this->_page = "index";
    }

    public function delete($id)
    {
        $patient = patient::find($id);
        Storage::disk('public')->delete($patient->photo_path);
        $patient->delete();
        session()->flash('message', 'Patient Deleted Successfully.');
    }

    public function render()
    {
        if (! hms_can('patients')) { abort(403); }

        if ($this->_page == "index") {
            return view('livewire.admins.patients.index', [
                'patients' => patient::query()
                    ->with('scheme:id,name')
                    ->when($this->search !== '', function ($q) {
                        $term = '%'.mb_strtolower($this->search).'%';
                        $q->where(function ($q) use ($term) {
                            $q->whereRaw('LOWER(name) LIKE ?', [$term])
                                ->orWhereRaw('LOWER(phone) LIKE ?', [$term])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                        });
                    })
                    ->when($this->schemeFilter !== '', fn ($q) => $q->where('scheme_id', $this->schemeFilter))
                    ->when($this->angioFilter === '1', fn ($q) => $q->whereHas('appointments', fn ($a) => $a->whereNotNull('angio_machine_id')))
                    ->when($this->angioFilter === '0', fn ($q) => $q->whereDoesntHave('appointments', fn ($a) => $a->whereNotNull('angio_machine_id')))
                    ->latest()
                    ->paginate(10),
                'schemes' => \App\Models\Scheme::orderBy('name')->get(['id', 'name']),
            ]);
        } else if ($this->_page == "create") {
            return view('livewire.admins.patients.create', [
                'schemes' => \App\Models\Scheme::orderBy('name')->get(['id', 'name']),
            ]);
        } else if ($this->_page == "edit") {
            return view('livewire.admins.patients.edit', [
                'schemes' => \App\Models\Scheme::orderBy('name')->get(['id', 'name']),
            ]);
        }
    }
}
