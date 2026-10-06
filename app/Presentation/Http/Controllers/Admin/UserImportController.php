<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Admin;

use App\Application\User\UseCases\ImportUsersUseCase;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Presentation\Http\Requests\Admin\ImportUsersRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class UserImportController extends Controller
{
    /**
     * Display the bulk user import page (spreadsheet is parsed client-side).
     */
    public function create(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return Inertia::render('Admin/Users/Import', [
            'departments' => $this->assignableDepartments(),
            'maxRowsPerRequest' => ImportUsersRequest::MAX_ROWS_PER_REQUEST,
        ]);
    }

    /**
     * Server-side dry-run validation of one chunk of rows (nothing is persisted).
     */
    public function validateRows(ImportUsersRequest $request, ImportUsersUseCase $useCase): JsonResponse
    {
        return $this->respond($request, $useCase, dryRun: true);
    }

    /**
     * Validate and create users for one chunk of rows.
     */
    public function store(ImportUsersRequest $request, ImportUsersUseCase $useCase): JsonResponse
    {
        return $this->respond($request, $useCase, dryRun: false);
    }

    private function respond(ImportUsersRequest $request, ImportUsersUseCase $useCase, bool $dryRun): JsonResponse
    {
        $results = $useCase->execute(
            rows: $request->rows(),
            defaultPassword: (string) $request->validated('password'),
            departmentId: $request->departmentId(),
            dryRun: $dryRun,
            adminUser: $request->user(),
        );

        return response()->json(['results' => $results]);
    }

    /**
     * Departments that newly created users may be attached to (as MEMBER).
     *
     * @return Collection<int, Department>
     */
    private function assignableDepartments(): Collection
    {
        return Department::query()
            ->where('type', 'specialized_team')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->toBase();
    }
}
