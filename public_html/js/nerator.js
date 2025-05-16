// Fungsi untuk generate PDF dengan tab baru
function generatePDF() {
    // Load library jsPDF dan autoTable secara dinamis
    const script1 = document.createElement('script');
    script1.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
    
    const script2 = document.createElement('script');
    script2.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js';
    
    script1.onload = function() {
        script2.onload = function() {
            createAndPreviewPDF();
        };
        document.head.appendChild(script2);
    };
    document.head.appendChild(script1);
}

function createAndPreviewPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Tambahkan logo
    const img = new Image();
    img.crossOrigin = 'Anonymous';
    img.src = 'images/Logo.png';
    
    img.onload = function() {
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);
        const imgData = canvas.toDataURL('image/png');
        doc.addImage(imgData, 'PNG', 15, 10, 30, 30);
        
        // Judul laporan
        doc.setFontSize(18);
        doc.text('Laporan Pesanan Tanjung Medika', 105, 20, { align: 'center' });
        
        // Informasi filter dan tanggal
        const currentDate = new Date().toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
        
        doc.setFontSize(11);
        doc.text(`Tanggal Cetak: ${currentDate}`, 105, 35, { align: 'center' });

        // Siapkan data tabel
        const headers = [
            "ID Pesanan", 
            "Total Harga", 
            "Status", 
            "Tanggal Pesanan", 
            "Waktu Pengambilan"
        ];

        const rows = [];
        const visibleRows = document.querySelectorAll('tbody tr:not([style*="display: none"])');

        visibleRows.forEach(row => {
            rows.push([
                row.querySelector('td:nth-child(1)').textContent,
                row.querySelector('td:nth-child(2)').textContent,
                row.querySelector('td:nth-child(3)').textContent,
                row.querySelector('td:nth-child(4)').textContent,
                row.querySelector('td:nth-child(5)').textContent
            ]);
        });

        // Tambahkan total harga di sebelah kiri
        const totalHarga = document.getElementById('totalHarga').textContent;
        rows.push([
            { content: 'TOTAL', styles: { fontStyle: 'bold' } },
            { content: totalHarga, styles: { fontStyle: 'bold' } },
            '', '', ''
        ]);

        // Buat tabel
        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 45,
            styles: {
                fontSize: 9,
                cellPadding: 3,
                halign: 'left'
            },
            headStyles: {
                fillColor: [16, 185, 129],
                textColor: 255,
                fontStyle: 'bold'
            },
            footStyles: {
                fontStyle: 'bold',
                fillColor: [240, 253, 244]
            }
        });

        // Buka PDF di tab baru
        const pdfOutput = doc.output('blob');
        const pdfUrl = URL.createObjectURL(pdfOutput);
        window.open(pdfUrl, '_blank');
    };
    
    img.onerror = function() {
        // Fallback tanpa logo
        doc.setFontSize(18);
        doc.text('Laporan Pesanan Tanjung Medika', 105, 15, { align: 'center' });
        
        doc.setFontSize(11);
        doc.text(`Tanggal Cetak: ${new Date().toLocaleDateString()}`, 105, 32, { align: 'center' });
        
        // Data tabel
        doc.autoTable({
            html: '#inventoryTable',
            startY: 40,
            styles: { 
                fontSize: 9,
                cellPadding: 3
            },
            headStyles: { 
                fillColor: [16, 185, 129],
                textColor: [255, 255, 255],
                fontStyle: 'bold'
            }
        });
        
        // Buka PDF di tab baru
        const pdfOutput = doc.output('blob');
        const pdfUrl = URL.createObjectURL(pdfOutput);
        window.open(pdfUrl, '_blank');
    };
}

// Inisialisasi event listener
document.addEventListener('DOMContentLoaded', function() {
    const printBtn = document.getElementById('printPdfBtn');
    if (printBtn) {
        printBtn.addEventListener('click', generatePDF);
    }
});