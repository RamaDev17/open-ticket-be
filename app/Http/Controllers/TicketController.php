<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketStoreRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Ticket::query();

            // Filter berdasarkan keyword (code atau title)
            $query->when($request->keyword, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('code', 'like', '%' . $request->keyword . '%')
                        ->orWhere('title', 'like', '%' . $request->keyword . '%');
                });
            });

            // Filter berdasarkan priority
            $query->when($request->priority, function ($q) use ($request) {
                $q->where('priority', $request->priority);
            });

            // Filter berdasarkan status
            $query->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            });

            // Filter berdasarkan role user
            $query->when(auth()->user()->role === 'user', function ($q) {
                $q->where('user_id', auth()->id());
            });

            // Optional: tambahkan relasi yang sering digunakan
            $query->with('user');
            $query->orderByDesc('created_at');

            $perPage = $request->input('limit', 10);
            $tickets = $query->paginate($perPage);

            return response()->json([
                'message' => 'Get ticket success',
                'meta' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
                'data' => TicketResource::collection($tickets)
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => "Error",
                'error' => $th->getMessage(),
            ], 500);
        }
    }


    public function store(TicketStoreRequest $request)
    {
        $data = $request->validated();

        DB::beginTransaction();

        try {
            $ticket = new Ticket();

            $ticket->user_id = auth()->user()->id;
            $ticket->code = 'TICKET-' . rand(100000, 999999);
            $ticket->title = $data['title'];
            $ticket->description = $data['description'];
            $ticket->priority = $data['priority'];

            $ticket->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Ticket created successfully',
                'data' => new TicketResource($ticket),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => "Error",
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
