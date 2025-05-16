// Fungsi untuk generate PDF
function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Membuat canvas untuk memproses gambar
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();
    
    // Handle CORS jika gambar dari domain berbeda
    img.crossOrigin = 'Anonymous';
    img.src = 'images/Logo.png';
    
    img.onload = function() {
        // Set ukuran canvas sesuai gambar
        canvas.width = img.width;
        canvas.height = img.height;
        
        // Gambar ke canvas (akan mempertahankan transparansi)
        ctx.drawImage(img, 0, 0);
        
        // Konversi canvas ke data URL dengan format PNG
        const imgData = canvas.toDataURL('image/png');
        
        // Tambahkan logo ke PDF (dengan transparansi)
        doc.addImage(imgData, 'PNG', 15, 10, 30, 30);
        
        // Judul laporan
        doc.setFontSize(18);
        doc.text('Laporan Inventory Tanjung Medika', 105, 20, { align: 'center' });
        
        // Tanggal cetak
        doc.setFontSize(12);
        doc.text(`Tanggal Cetak: ${new Date().toLocaleDateString()}`, 105, 30, { align: 'center' });
        
        // Data tabel dengan jarak lebih dari header (startY: 45)
        doc.autoTable({
            html: '#inventoryTable',
            startY: 45,
            styles: { 
                fontSize: 10,
                cellPadding: 3
            },
            headStyles: { 
                fillColor: [66, 165, 245],
                textColor: [255, 255, 255]
            },
            didParseCell: function(data) {
                if (data.column.index === 6) {
                    data.cell.text = '';
                }
            },
            margin: { top: 10 }
        });
        
        // Buka PDF dalam tab baru untuk preview
        const pdfOutput = doc.output('blob');
        const pdfUrl = URL.createObjectURL(pdfOutput);
        
        // Buka PDF langsung dengan viewer bawaan browser
        window.open(pdfUrl, '_blank');
    };
    
    img.onerror = function() {
        // Fallback tanpa logo jika gambar gagal dimuat
        doc.setFontSize(18);
        doc.text('Laporan Inventory Tanjung Medika', 105, 15, { align: 'center' });
        doc.setFontSize(12);
        doc.text(`Tanggal Cetak: ${new Date().toLocaleDateString()}`, 105, 25, { align: 'center' });
        doc.autoTable({
            html: '#inventoryTable',
            startY: 35,
            styles: { fontSize: 10 }, 
            headStyles: { fillColor: [66, 165, 245] },
            didParseCell: function(data) {
                if (data.column.index === 6) {
                    data.cell.text = '';
                }
            }
        });
        
        // Tetap buka preview
        const pdfOutput = doc.output('blob');
        const pdfUrl = URL.createObjectURL(pdfOutput);
        window.open(pdfUrl, '_blank');
    };
}