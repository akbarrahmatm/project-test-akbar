<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Label;
use App\Models\Ticket;
use App\Models\TicketLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdminTicketController extends Controller
{
    public function list(Request $request)
    {
        $selected_category = $request->query('category');
        $selected_priority = $request->query('priority');
        $selected_status = $request->query('status');

        $query = Ticket::with(['categories', 'labels']);

        if ($selected_category) {
            $query->whereHas('categories', function ($category) use ($selected_category) {
                $category->where('category_id', $selected_category);
            });
        }

        if ($selected_priority && in_array($selected_priority, ['low', 'normal', 'high', 'urgent'])) {
            $query->where('priority', $selected_priority);
        }

        if ($selected_status && in_array($selected_status, ['open', 'close'])) {
            $query->where('status', $selected_status);
        }

        $data = [
            'title' => 'Support Tickets',
            'tickets' => $query->orderBy('created_at', 'desc')->paginate(10),
            'categories' => Category::orderBy('category_name', 'asc')->get(),
            'selected_category' => $selected_category,
            'selected_priority' => $selected_priority,
            'selected_status' => $selected_status
        ];

        return view('admin.tickets.list', $data);
    }
    public function edit($id)
    {
        $data = [
            'title' => 'Edit Tickets',
            'ticket' => Ticket::with(['categories', 'labels', 'assigned_agent', 'user'])->findOrFail($id),
            'labels' => Label::orderBy('label_name', 'asc')->get(),
            'categories' => Category::orderBy('category_name', 'asc')->get(),
            'agents' => User::where(['role' => 'agent'])->orderBy('id', 'asc')->get()
        ];


        return view('admin.tickets.edit', $data);
    }
    public function detail($id)
    {
        $ticket = Ticket::with(['categories', 'labels', 'assigned_agent', 'attachments', 'comments' => function ($query) {
            $query->orderBy('created_at', 'asc')->with('user');
        }])->findOrFail($id);

        $data = [
            'title' => 'Support Ticket Detail',
            'ticket' => $ticket
        ];

        return view('admin.tickets.detail', $data);
    }
    public function update(Request $request)
    {
        $validate = $request->validate([
            'title' => 'required|min:5',
            'description' => 'required',
            'labels' => 'required|array|min:1',
            'categories' => 'required|array|min:1',
            'priority' => [
                'required',
                Rule::in(['low', 'normal', 'high', 'urgent'])
            ],
            'status' => ['required', Rule::in(['open', 'close'])]
        ]);



        $agent = User::find($request->assigned_agent);
        $ticket_id = $request->ticket_id;


        if ($agent && $agent->role != 'agent') {
            return redirect('/admin/tickets/edit/' . $ticket_id)->with('error', 'You assign user with role other than agent');
        }

        $ticket = Ticket::find($ticket_id);

        if ($agent) {
            $data = [
                'title' => $validate['title'],
                'description' => $validate['description'],
                'priority' => $validate['priority'],
                'assigned_agent_id' => $request->assigned_agent,
                'status' => $validate['status'],
            ];
        } else {
            $data = [
                'title' => $validate['title'],
                'description' => $validate['description'],
                'priority' => $validate['priority'],
                'status' => $validate['status'],

            ];
        }

        $ticket->update($data);

        $labels = array_map('intval', $request->labels);
        $categories = array_map('intval', $request->categories);

        $ticket->labels()->sync($labels);
        $ticket->categories()->sync($categories);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'updated'
        ]);

        return redirect('/admin/tickets')->with('success', 'Ticket successfully updated');
    }
    public function delete(Request $request)
    {
        $id = $request->id;

        Ticket::where('id', $id)->delete();

        return redirect("/admin/tickets")->with('success', "Ticket Successfully Deleted");
    }
    public function changestatus(Request $request)
    {
        $ticket = Ticket::findOrFail($request->id);

        $current_status = $ticket->status;

        if ($current_status == "open") {
            $data = [
                'status' => 'close'
            ];
        } else {
            $data = [
                'status' => 'open'
            ];
        }

        $ticket->update($data);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'updated'
        ]);

        return redirect('/admin/tickets/detail/' . $ticket->id)->with('success', 'Ticket status successfully changed');
    }
}
