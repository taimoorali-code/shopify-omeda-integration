{{-- resources/views/omeda/logs.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="container mt-4">
        <h2 class="mb-4">🧾 Omeda API Logs</h2>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Customer ID</th>
                        <th>Transaction ID</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        @php
                            $resp = $log->response;
                            $info = $resp['body']['ResponseInfo'][0] ?? [];
                            $status = isset($info['CustomerId']) ? '✅ Success' : '❌ Failed';
                        @endphp

                        <tr>
                            <td>{{ $log->id }}</td>
                            <td>{{ $info['CustomerId'] ?? 'N/A' }}</td>
                            <td>{{ $info['TransactionId'] ?? 'N/A' }}</td>
                            <td>
                                <span class="badge {{ $status === '✅ Success' ? 'bg-success' : 'bg-danger' }}">
                                    {{ $status }}
                                </span>
                            </td>
                            <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $logs->links() }}
        </div>
    </div>
@endsection
