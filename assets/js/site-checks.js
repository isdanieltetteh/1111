document.addEventListener("DOMContentLoaded", () => {
    const urlInput       = document.getElementById("url");
    const backlinkInput  = document.getElementById("backlink_url");

    // 🔹 Live URL check (only validates, no auto-fetch of meta/logo)
    if (urlInput) {
        urlInput.addEventListener("blur", () => {
            if (!urlInput.value) return;
            fetch(`api/check_url.php?url=${encodeURIComponent(urlInput.value)}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert("URL Error: " + data.error);
                        urlInput.value = "";
                    }
                })
                .catch(() => {
                    alert("Could not check URL. Please try again.");
                });
        });
    }

    // 🔹 Backlink check
    if (backlinkInput) {
        backlinkInput.addEventListener("blur", () => {
            if (!backlinkInput.value) return;
            fetch(`api/check_backlink.php?url=${encodeURIComponent(backlinkInput.value)}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert("Backlink Error: " + data.error);
                        backlinkInput.value = "";
                    }
                })
                .catch(() => {
                    alert("Could not check backlink. Please try again.");
                });
        });
    }
});
