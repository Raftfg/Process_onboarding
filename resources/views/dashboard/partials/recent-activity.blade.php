<div class="card">
    <div class="card-header">
        <h3 class="card-title">Activité récente</h3>
        <a href="{{ route('dashboard.activities') }}" style="color: var(--primary-color); text-decoration: none; font-size: 14px;">Voir tout →</a>
    </div>
    
    <div style="position: relative; padding-left: 30px;">
        @forelse($activities as $activity)
            <div style="position: relative; padding-bottom: 25px; border-left: 2px solid var(--border-color); padding-left: 25px;">
                
                <div style="display: flex; align-items: start; gap: 15px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(102, 126, 234, 0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
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
                            {{ $activity->created_at->diffForHumans() }}
                        </div>
                    </div>
                    
                    <div style="padding: 4px 12px; color: var(--primary-color); font-size: 12px; font-weight: 600;">
                        {{ $activity->type }}
                    </div>
                </div>
            </div>
        @empty
                <div>Aucune activité récente</div>
        @endforelse
    </div>
</div>
