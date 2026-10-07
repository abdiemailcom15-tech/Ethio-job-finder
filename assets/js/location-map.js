(() => {
    const defaultCenter = [9.145, 40.4897];

    document.querySelectorAll('[data-location-map]').forEach((element) => {
        const status = element.parentElement.querySelector('[data-map-status]');
        if (!window.L) {
            if (status) {
                status.textContent = 'The map could not be loaded. You can still enter the coordinates manually.';
            }
            return;
        }

        const picker = element.hasAttribute('data-location-map-picker');
        const latitudeInput = picker && element.parentElement.querySelector('[name="latitude"]');
        const longitudeInput = picker && element.parentElement.querySelector('[name="longitude"]');
        const initialLatitude = picker ? Number(latitudeInput.value) : Number(element.dataset.latitude);
        const initialLongitude = picker ? Number(longitudeInput.value) : Number(element.dataset.longitude);
        const hasInitialCoordinates = Number.isFinite(initialLatitude)
            && Number.isFinite(initialLongitude)
            && Math.abs(initialLatitude) <= 90
            && Math.abs(initialLongitude) <= 180
            && (!picker || (latitudeInput.value !== '' && longitudeInput.value !== ''));
        const initialZoom = hasInitialCoordinates ? 15 : 6;
        const map = window.L.map(element, { scrollWheelZoom: false })
            .setView(hasInitialCoordinates ? [initialLatitude, initialLongitude] : defaultCenter, initialZoom);

        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);
        if (status && !picker) {
            status.textContent = 'Map centered on the exact job coordinates.';
        }

        let marker = null;
        const showMarker = (latitude, longitude, draggable) => {
            const position = [latitude, longitude];
            if (!marker) {
                marker = window.L.marker(position, { draggable }).addTo(map);
                marker.bindPopup('Exact job location');
                if (draggable) {
                    marker.on('dragend', () => {
                        const point = marker.getLatLng();
                        latitudeInput.value = point.lat.toFixed(7);
                        longitudeInput.value = point.lng.toFixed(7);
                        status.textContent = `Selected ${latitudeInput.value}, ${longitudeInput.value}.`;
                    });
                }
            } else {
                marker.setLatLng(position);
            }
        };

        if (hasInitialCoordinates) {
            showMarker(initialLatitude, initialLongitude, picker);
        }

        if (!picker) {
            return;
        }

        map.on('click', (event) => {
            const latitude = event.latlng.lat;
            const longitude = event.latlng.lng;
            latitudeInput.value = latitude.toFixed(7);
            longitudeInput.value = longitude.toFixed(7);
            showMarker(latitude, longitude, true);
            status.textContent = `Selected ${latitudeInput.value}, ${longitudeInput.value}.`;
        });

        const updateFromInputs = () => {
            if (latitudeInput.value === '' && longitudeInput.value === '') {
                if (marker) {
                    marker.remove();
                    marker = null;
                }
                status.textContent = 'Select the exact job location on the map or enter coordinates.';
                return;
            }

            const latitude = Number(latitudeInput.value);
            const longitude = Number(longitudeInput.value);
            if (latitudeInput.value === '' || longitudeInput.value === ''
                || !Number.isFinite(latitude) || !Number.isFinite(longitude)
                || Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
                status.textContent = 'Enter a valid latitude and longitude, or select the location on the map.';
                return;
            }

            showMarker(latitude, longitude, true);
            map.setView([latitude, longitude], Math.max(map.getZoom(), 15));
            status.textContent = `Selected ${latitude.toFixed(7)}, ${longitude.toFixed(7)}.`;
        };

        latitudeInput.addEventListener('change', updateFromInputs);
        longitudeInput.addEventListener('change', updateFromInputs);
    });
})();
