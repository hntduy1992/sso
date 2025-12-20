<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // 1. Lấy tham số từ request (Vuetify gửi lên)
        $search = $request->input('search');
        $perPage = $request->input('itemsPerPage', 10); // Mặc định 10 items/trang
        $sortBy = $request->input('sortBy', []);

        // 2. Truy vấn dữ liệu
        $query = User::query()->with(['roles:id,name']);

        // Tìm kiếm nếu có
        if ($search) {
            $query->where('full_name', 'like', "%{$search}%");
            $query->orWhere('username', 'like', "%{$search}%");
        }

        // Sắp xếp nếu có
        if (!empty($sortBy)) {
            foreach ($sortBy as $sort) {
                $query->orderBy($sort['key'], $sort['order']);
            }
        }

        // 3. Phân trang và trả về Inertia
        return Inertia::render('Users/IndexPage', [
            'users' => $query->paginate($perPage)->withQueryString(),
            'filters' => $request->only(['search', 'itemsPerPage', 'sortBy']),
            'available_roles'=>Role::all()->pluck('name')->toArray(),
        ]);
    }
}
