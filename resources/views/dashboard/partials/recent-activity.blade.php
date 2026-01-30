<div class="card">
    <div class="card-header">
        <h3 class="card-title">Activité récente</h3>
        <a href="{{ route('dashboard.activities') }}" style="color: var(--primary-color); text-decoration: none; font-size: 14px;">Voir tout →</a>
    </div>
    
    <div style="position: relative; padding-left: 30px;">
        @forelse($activities as $activity)
            <div style="position: relative; padding-bottom: 25px; border-left: 2px solid var(--border-color); padding-left: 25px;">
                <div style="position: absolute; left: -8px; top: 0; width: 14px; height: 14px; border-radius: 50%; background: var(--primary-color); border: 2px solid white;"></div>
                
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
                    
                    <div style="padding: 4px 12px; background: rgba(102, 126, 234, 0.1); color: var(--primary-color); border-radius: 12px; font-size: 12px; font-weight: 600;">
                        {{ $activity->type }}
                    </div>
                </div>
            </div>
        @empty
            <div style="padding: 40px; text-align: center; color: #999;">
                <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                <div>Aucune activité récente</div>
            </div>
        @endforelse
    </div>
</div>
