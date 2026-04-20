<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::where('user_id', $request->user()->id);

        // Filtres optionnels
        if ($request->has('completed')) {
            $query->where('completed', $request->boolean('completed'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->has('due_today')) {
            $query->dueToday();
        }

        $tasks = $query->orderBy('due_date')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $tasks,
            'meta' => [
                'total' => $tasks->count(),
                'completed' => $tasks->where('completed', true)->count(),
                'pending' => $tasks->where('completed', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'due_date' => 'nullable|date|after:now',
        ]);

        $task = Task::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $task,
            'message' => 'Task created successfully',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $task = Task::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['data' => $task]);
    }

    public function update(Request $request, $id)
    {
        $task = Task::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'completed' => 'sometimes|boolean',
            'priority' => 'sometimes|in:low,medium,high',
            'due_date' => 'nullable|date',
        ]);

        $task->update($validated);

        return response()->json([
            'data' => $task,
            'message' => 'Task updated successfully',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $task = Task::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully',
        ]);
    }

    public function toggleComplete(Request $request, $id)
    {
        $task = Task::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $task->completed = !$task->completed;
        $task->save();

        return response()->json([
            'data' => $task,
            'message' => $task->completed ? 'Task completed' : 'Task marked as pending',
        ]);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        $stats = [
            'total' => Task::where('user_id', $userId)->count(),
            'completed' => Task::where('user_id', $userId)->completed()->count(),
            'pending' => Task::where('user_id', $userId)->pending()->count(),
            'high_priority' => Task::where('user_id', $userId)->highPriority()->count(),
            'overdue' => Task::where('user_id', $userId)
                ->where('completed', false)
                ->where('due_date', '<', now())
                ->count(),
            'due_today' => Task::where('user_id', $userId)->dueToday()->count(),
        ];

        return response()->json(['data' => $stats]);
    }
}
