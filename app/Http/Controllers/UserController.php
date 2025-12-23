<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\CreateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // 1. Lấy tham số từ request (Vuetify gửi lên)
        $search = $request->input('search');
        $page = $request->input('page', 1); // Mặc định 10 items/trang
        $perPage = $request->input('perPage', 10);; // Mặc định 10 items/trang
        // 2. Truy vấn dữ liệu
        $query = User::query()->with(['roles:id,name']);
        // Tìm kiếm nếu có
        if ($search) {
            $query->where('full_name', 'like', "%{$search}%");
            $query->orWhere('username', 'like', "%{$search}%");
        }

        // 3. Phân trang và trả về Inertia
        return Inertia::render('Users/IndexPage', [
            'users' => $query->paginate(perPage: $perPage, page: $page)->withQueryString(),
            'filters' => $request->only(['search', 'page', 'perPage']),
            'available_roles' => Role::all()->pluck('name')->toArray(),
        ]);
    }

    public function store(CreateUserRequest $request)
    {
        $request->validated();
        $newUser = User::create([
            'username' => $request->input('username'),
            'password' => Hash::make($request->input('password')),
            'full_name' => $request->input('full_name')
        ]);
        return response()->json(['user'=>$newUser]);
    }
}
