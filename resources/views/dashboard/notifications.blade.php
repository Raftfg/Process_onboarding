@extends('layouts.dashboard')

@section('title', 'Notifications')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 28px; font-weight: 600;">Notifications</h1>
            <p style="color: #666;">Toutes vos notifications</p>
        </div>
        <form action="{{ route('dashboard.notifications.read-all') }}" method="POST" style="display: inline;">
            @csrf
            <button type="submit" style="background: var(--primary-color); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                Tout marquer comme lu
            </button>
        </form>
    </div>

    <div class="card">
        @forelse($notifications as $notification)
            <div style="padding: 20px; border-bottom: 1px solid var(--border-color); {{ $notification->isRead() ? 'opacity: 0.7;' : 'background: rgba(102, 126, 234, 0.05);' }}">
                <div style="display: flex; justify-content: space-between; align-items: start; gap: 15px;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                            <h3 style="font-size: 16px; font-weight: 600;">{{ $notification->title }}</h3>
                            <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: {{ $notification->type === 'error' ? '#fee2e2' : ($notification->type === 'success' ? '#d1fae5' : ($notification->type === 'warning' ? '#fef3c7' : '#dbeafe')) }}; color: {{ $notification->type === 'error' ? '#991b1b' : ($notification->type === 'success' ? '#065f46' : ($notification->type === 'warning' ? '#92400e' : '#1e40af')) }};">
                                {{ ucfirst($notification->type) }}
                            </span>
                            @if(!$notification->isRead())
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--primary-color); display: inline-block;"></span>
                            @endif
                        </div>
                        <p style="color: #666; font-size: 14px; margin-bottom: 8px;">{{ $notification->message }}</p>
                        <div style="color: #999; font-size: 12px;">{{ $notification->created_at->format('d/m/Y H:i') }} ({{ $notification->created_at->diffForHumans() }})</div>
                    </div>
                    @if(!$notification->isRead())
                        <form action="{{ route('dashboard.notifications.read', $notification->id) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" style="padding: 6px 12px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer; font-size: 12px;">
                                Marquer comme lu
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div style="padding: 40px; text-align: center; color: #999;">
                <div style="font-size: 48px; margin-bottom: 10px;">🔔</div>
                <div>Aucune notification</div>
            </div>
        @endforelse
    </div>
    
    <div style="margin-top: 20px;">
        {{ $notifications->links() }}
    </div>
@endsection
