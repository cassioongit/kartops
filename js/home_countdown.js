/**
 * Home Countdown & Calendar Features
 */

function startCountdown(targetDateStr) {
    // targetDateStr format: YYYY-MM-DD HH:MM:SS (ISOish) or just compatible with Date constructor
    const targetDate = new Date(targetDateStr.replace(' ', 'T')); // Ensure ISO format for Safari compatibility

    function update() {
        const now = new Date();
        const diff = targetDate - now;

        if (diff <= 0) {
            document.getElementById('countdown-days').textContent = '00';
            document.getElementById('countdown-hours').textContent = '00';
            document.getElementById('countdown-minutes').textContent = '00';
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        // const seconds = Math.floor((diff % (1000 * 60)) / 1000); 

        document.getElementById('countdown-days').textContent = String(days).padStart(2, '0');
        document.getElementById('countdown-hours').textContent = String(hours).padStart(2, '0');
        document.getElementById('countdown-minutes').textContent = String(minutes).padStart(2, '0');
    }

    update();
    setInterval(update, 1000);
}

function downloadICS(event) {
    // event expects: { nome, data, hora, local }
    // data: YYYY-MM-DD, hora: HH:MM:SS

    const startStr = event.data + 'T' + event.hora;
    const startDate = new Date(startStr);
    const endDate = new Date(startDate.getTime() + 2 * 60 * 60 * 1000); // 2 hours duration

    function formatICSDate(date) {
        return date.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
    }

    const dtStart = formatICSDate(startDate);
    const dtEnd = formatICSDate(endDate);
    const dtStamp = formatICSDate(new Date());

    const uid = 'event-' + startDate.getTime() + '@oskarteiro.com.br';

    const icsContent = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Oskarteiro//Kart Championship//EN',
        'BEGIN:VEVENT',
        `UID:${uid}`,
        `DTSTAMP:${dtStamp}`,
        `DTSTART:${dtStart}`,
        `DTEND:${dtEnd}`,
        `SUMMARY:${event.nome}`,
        `DESCRIPTION:Campeonato de Kart - ${event.nome}`,
        `LOCATION:${event.local}`,
        'END:VEVENT',
        'END:VCALENDAR'
    ].join('\r\n');

    const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${event.nome.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.ics`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
