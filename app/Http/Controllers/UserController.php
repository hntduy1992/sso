<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
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
        $query = User::query()->with(['roles:id,name'])->when(!$request->user()->hasRole('super-admin'))
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'super-admin');
            });

        // Tìm kiếm nếu có
        if ($search) {
            $query->where('full_name', 'like', "%{$search}%");
            $query->orWhere('username', 'like', "%{$search}%");
        }

        $roles = Role::query()->when(!$request->user()->hasRole('super-admin'))->where('name', '!=', 'super-admin')->get(['id', 'name']);

        // 3. Phân trang và trả về Inertia
        return Inertia::render('Users/IndexPage', [
            'users' => $query->paginate(perPage: $perPage, page: $page)->withQueryString(),
            'filters' => $request->only(['search', 'page', 'perPage']),
            'roles' => $roles,
        ]);
    }

    public function store(CreateUserRequest $request)
    {
        $request->validated();
        if (Role::query()->where('id', $request->input('role'))->first()->name === 'super-admin' && !$request->user()->hasRole('super-admin')) {
            return redirect()->back()->with('error', 'Bạn không có quyền thực hiện tác vụ này!');
        }
        $newUser = User::create([
            'username' => $request->input('username'),
            'password' => Hash::make($request->input('password')),
            'full_name' => $request->input('full_name')
        ]);
        if ($newUser) {
            $newUser->syncRoles($request->input('role'));
        }
        return redirect()->back()->with('success', 'Thêm mới thành công!');
    }

    public function update(UpdateUserRequest $request, $id)
    {
        $request->validated();

        //check request->role['super-admin'] && user->roles != super-admin
        $newRole = Role::query()->where('id', $request->input('role'))->first();
        if ($newRole->name === 'super-admin' && !$request->user()->hasRole('super-admin')) {
            return redirect()->back()->with('error', 'Bạn không có quyền thực hiện tác vụ này!');
        }
        $user = User::findOrFail($id);
        $user->full_name = $request->input('full_name');
        $user->syncRoles($request->input('role'));
        $result = $user->saveOrFail();
        if (!$result) {
            return redirect()->back()->with('error', 'Cập nhật thất bại!');
        }
        return redirect()->back()->with('success', 'Cập nhật thành công!');
    }

}
