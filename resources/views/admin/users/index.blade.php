@extends('admin.layouts.app')

@section('title', 'Users')
@section('heading', 'Users')
@section('breadcrumbs')
    <span>Admin</span><span>Users</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Filter users</h2>
                <div class="btn-row">
                    <a href="{{ route('admin.users.create') }}" class="btn btn--primary">+ Add User</a>
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.users.index') }}" class="row">
                    <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" class="input" value="{{ request('q') }}"
                               placeholder="Name or email">
                    </div>

                    <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                        <label for="company_id">Company</label>
                        <select id="company_id" name="company_id" class="select">
                            <option value="">All companies</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>
                                    {{ $company->name }}@unless ($company->status) (inactive)@endunless
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field col-md-2 col-lg-2 col-sm-12 col-xs-12">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="select">
                            <option value="">All</option>
                            <option value="1" @selected(request('status') === '1')>Active</option>
                            <option value="0" @selected(request('status') === '0')>Inactive</option>
                        </select>
                    </div>

                    <div class="field field--actions col-md-2 col-lg-2 col-sm-12 col-xs-12">
                        <button type="submit" class="btn btn--primary">Apply</button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>All users <span class="muted small">({{ $users->total() }})</span></h2>
            </div>

            @if ($users->isEmpty())
                <x-empty-state
                    title="No users found"
                    message="Nobody matches the current filters. Add a user and map them to the companies they should be able to see."
                    :action="route('admin.users.create')"
                    action-label="+ Add User"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Companies</th>
                                <th>Status</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td class="title">{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        {{-- The whole point of the screen: what
                                             this person can actually reach. --}}
                                        @forelse ($user->companies as $company)
                                            <span class="badge badge--muted">{{ $company->name }}</span>
                                        @empty
                                            <span class="badge badge--off">
                                                <span class="dot"></span>No access
                                            </span>
                                        @endforelse
                                    </td>
                                    <td>
                                        <span class="badge badge--{{ $user->status ? 'on' : 'off' }}">
                                            <span class="dot"></span>{{ $user->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn--sm">Edit</a>
                                            <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn--sm"
                                                        data-busy="Saving...">
                                                    {{ $user->status ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pagination-wrap">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
