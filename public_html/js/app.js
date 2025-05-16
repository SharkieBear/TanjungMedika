// Pendaftaran Service Worker
if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
        navigator.serviceWorker
            .register("/sw.js") // ✅ Diperbaiki: tidak perlu scope jika ingin global
            .then((reg) => {
                console.log("Service Worker terdaftar dengan scope:", reg.scope);
            })
            .catch((err) => {
                console.error("Pendaftaran Service Worker gagal:", err);
            });
    });
}

// Fungsi untuk menampilkan prompt install PWA
let deferredPrompt;

window.addEventListener("beforeinstallprompt", (e) => {
    e.preventDefault();
    deferredPrompt = e;

    // Tampilkan button install
    const installButton = document.createElement("button");
    installButton.textContent = "Install App";
    installButton.style.position = "fixed";
    installButton.style.bottom = "20px";
    installButton.style.right = "20px";
    installButton.style.zIndex = "9999";
    installButton.style.padding = "10px 20px";
    installButton.style.backgroundColor = "#16a34a";
    installButton.style.color = "white";
    installButton.style.border = "none";
    installButton.style.borderRadius = "5px";
    installButton.style.cursor = "pointer";

    installButton.addEventListener("click", () => {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === "accepted") {
                console.log("User accepted install prompt");
            }
            deferredPrompt = null;
            installButton.remove(); // hapus tombol setelah di-klik
        });
    });

    document.body.appendChild(installButton);
});

// Fungsi untuk meminta izin notifikasi
function requestNotificationPermission() {
    if ("Notification" in window) {
        Notification.requestPermission().then((permission) => {
            if (permission === "granted") {
                console.log("Notification permission granted.");
                subscribeToPush();
            }
        });
    }
}

// Fungsi untuk subscribe ke push notification
function subscribeToPush() {
    if ("serviceWorker" in navigator) {
        navigator.serviceWorker.ready.then((registration) => {
            registration.pushManager
                .subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array("BPYgv4pAa-uv3ziki3vgccvxca54tEP8ZHbmzvHCFEZ8Uxqvq2c13eOZIbx7xn3in6uow3h58Uz5_XeQBxLJbrY"),
                })
                .then((subscription) => {
                    console.log("Push subscription successful:", subscription);
                    saveSubscription(subscription);
                })
                .catch((error) => {
                    console.error("Push subscription failed:", error);
                });
        });
    }
}

// Helper function untuk konversi key
function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Fungsi untuk menyimpan subscription ke server
function saveSubscription(subscription) {
    fetch("/save-subscription.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(subscription),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error("Gagal menyimpan subscription");
            }
            return response.json();
        })
        .then((data) => {
            console.log("Subscription berhasil disimpan:", data);
        })
        .catch((error) => {
            console.error("Error:", error);
        });
}

// Panggil fungsi request permission saat halaman dimuat
window.addEventListener("load", () => {
    requestNotificationPermission();
});

// Cek mode PWA
window.addEventListener("load", () => {
    if (window.matchMedia("(display-mode: standalone)").matches) {
        console.log("Running in PWA mode");
    } else {
        console.log("Running in browser mode");
    }
});
