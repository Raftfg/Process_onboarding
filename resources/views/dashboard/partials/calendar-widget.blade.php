<div class="card">
    <div class="card-header">
        <h3 class="card-title">Calendrier</h3>
    </div>
    
    <div id="calendar-widget" style="padding: 10px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <button onclick="previousMonth()" style="background: none; border: none; cursor: pointer; padding: 5px 10px; font-size: 18px;">‹</button>
            <div style="font-weight: 600;" id="calendar-month">{{ now()->format('F Y') }}</div>
            <button onclick="nextMonth()" style="background: none; border: none; cursor: pointer; padding: 5px 10px; font-size: 18px;">›</button>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; margin-bottom: 10px;">
            @foreach(['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $day)
                <div style="text-align: center; font-size: 12px; font-weight: 600; color: #666; padding: 5px;">{{ $day }}</div>
            @endforeach
        </div>
        
        <div id="calendar-days" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px;">
            <!-- Les jours seront générés par JavaScript -->
        </div>
    </div>
</div>

<script>
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();

    function renderCalendar() {
        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const today = new Date();
        
        const monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        document.getElementById('calendar-month').textContent = monthNames[currentMonth] + ' ' + currentYear;
        
        const calendarDays = document.getElementById('calendar-days');
        calendarDays.innerHTML = '';
        
        // Jours vides au début
        const startDay = firstDay === 0 ? 6 : firstDay - 1; // Lundi = 0
        for (let i = 0; i < startDay; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.style.cssText = 'padding: 8px; text-align: center;';
            calendarDays.appendChild(emptyDay);
        }
        
        // Jours du mois
        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = document.createElement('div');
            dayElement.style.cssText = 'padding: 8px; text-align: center; border-radius: 6px; cursor: pointer; transition: background 0.3s;';
            
            const isToday = currentYear === today.getFullYear() && 
                           currentMonth === today.getMonth() && 
                           day === today.getDate();
            
            if (isToday) {
                dayElement.style.background = 'var(--primary-color)';
                dayElement.style.color = 'white';
                dayElement.style.fontWeight = '600';
            }
            
            dayElement.textContent = day;
            dayElement.onmouseover = function() {
                if (!isToday) {
                    this.style.background = 'var(--bg-color)';
                }
            };
            dayElement.onmouseout = function() {
                if (!isToday) {
                    this.style.background = 'transparent';
                }
            };
            
            calendarDays.appendChild(dayElement);
        }
    }

    function previousMonth() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        renderCalendar();
    }

    function nextMonth() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        renderCalendar();
    }

    // Initialiser le calendrier
    renderCalendar();
</script>
