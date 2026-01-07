<?php

namespace App\Http\Controllers;

use App\Http\Requests\Department\CreateDepartmentRequest;
use App\Models\Department;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use mysql_xdevapi\Exception;

class DepartmentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('department.list');
        // 1. Lấy tham số từ request (Vuetify gửi lên)
        $search = $request->input('search');
        $page = $request->input('page', 1); // Mặc định 10 items/trang
        $perPage = $request->input('perPage', 10); // Mặc định 10 items/trang
        // 2. Truy vấn dữ liệu
        $query = Department::query();

        // Tìm kiếm nếu có
        $query->when($search)->where('name', 'like', "%{$search}%");

        return Inertia::render('Departments/IndexPage', [
            'departments' => $query->paginate(perPage: $perPage, page: $page)->withQueryString(),
            'filters' => $request->only(['search', 'page', 'perPage']),
        ]);
    }

    public function store(CreateDepartmentRequest $request)
    {
        $request->validated();
        $slug = Str::slug($request->input('name'));
        try {
            $department = Department::create([
                'name' => $request->input('name'),
                'slug' => $slug,
                'is_active' => $request->input('is_active'),
                'sort' => $request->input('sort') ?? 0,
                'parent_id' => $request->input('parent_id') ?? 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['err' => $e]);
        }
        return redirect()->back()->with('success', 'Thêm mới thành công!');
    }
}
