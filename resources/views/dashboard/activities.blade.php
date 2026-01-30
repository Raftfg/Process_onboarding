@extends('layouts.dashboard')

@section('title', 'Activités')

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 600;">Activités</h1>
        <p style="color: #666;">Historique de toutes les activités du système</p>
    </div>

    <div class="card">
        <div style="position: relative; padding-left: 30px;">
            @forelse($activities as $activity)
                <div style="position: relative; padding-bottom: 25px; border-left: 2px solid var(--border-color); padding-left: 25px; margin-bottom: 20px;">
                    <div style="position: absolute; left: -8px; top: 0; width: 14px; height: 14px; border-radius: 50%; background: var(--primary-color); border: 2px solid white;"></div>
                    
                    <div style="display: flex; align-items: start; gap: 15px;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(102, 126, 234, 0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600;">
                            {{ strtoupper(substr($activity->user->name ?? 'U', 0, 1)) }}
                        </div>
                        
                        <div style="flex: 1;">
                            <div style="font-weight: 600; margin-bottom: 5px;">
                                {{ $activity->user->name ?? 'Utilisateur' }}
                            </div>
                            <div style="color: #666; font-size: 14px; margin-bottom: 5px;">
                                {{ $activity->description }}
                            </div>
                            <div style="color: #999; font-size: 12px;">
                                {{ $activity->created_at->format('d/m/Y H:i') }} ({{ $activity->created_at->diffForHumans() }})
                            </div>
                        </div>
                        
                        <div style="padding: 4px 12px; background: rgba(102, 126, 234, 0.1); color: var(--primary-color); border-radius: 12px; font-size: 12px; font-weight: 600;">
                            {{ $activity->type }}
                        </div>
                    </div>
                </div>
            @empty
                <div style="padding: 40px; text-align: center; color: #999;">
                    <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                    <div>Aucune activité trouvée</div>
                </div>
            @endforelse
        </div>
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color);">
            {{ $activities->links() }}
        </div>
    </div>
@endsection
