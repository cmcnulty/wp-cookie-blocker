/**
 * WP Cookie Blocker Script
 *
 * Blocks cookies based on regex patterns configured in the admin
 */
(function() {
    // Get settings from WordPress
    const patterns = typeof wpCookieBlocker !== 'undefined' && wpCookieBlocker.patterns
        ? wpCookieBlocker.patterns
        : []; // No default - don't block anything if not configured

    const enableLogging = typeof wpCookieBlocker !== 'undefined'
        ? !!wpCookieBlocker.enableLogging
        : false;

    // Compile regex patterns - treat all inputs as regex
    const regexPatterns = patterns.map(pattern => {
        try {
            return new RegExp(pattern);
        } catch (e) {
            if (enableLogging) {
                console.error(`Invalid regex pattern: ${pattern}`, e);
            }
            return null; // Skip invalid patterns
        }
    }).filter(pattern => pattern !== null); // Remove any null patterns

    if (enableLogging) {
        console.log('Cookie Blocker active with patterns:', patterns);
    }

    // Skip if no valid patterns
    if (regexPatterns.length === 0) {
        if (enableLogging) {
            console.log('No valid patterns to block - Cookie Blocker inactive');
        }
        return;
    }

    // Override the document.cookie setter
    const originalDescriptor = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');
    const originalSetter = originalDescriptor.set;

    Object.defineProperty(Document.prototype, 'cookie', {
        configurable: true,
        get: originalDescriptor.get,
        set: function(value) {
            // Check if this cookie should be blocked
            const cookieParts = value.split('=');
            if (cookieParts.length >= 1) {
                const cookieName = cookieParts[0].trim();

                // Check if the cookie matches any of our patterns
                const shouldBlock = regexPatterns.some(regex => regex.test(cookieName));

                if (shouldBlock) {
                    if (enableLogging) {
                        console.log(`Blocked cookie: ${cookieName} (matched pattern)`);
                    }
                    return; // Don't set the cookie
                }
            }

            // Set the cookie using the original setter if not blocked
            return originalSetter.call(this, value);
        }
    });

    // Function to clean existing cookies
    function cleanBlockedCookies() {
        const cookies = document.cookie.split(';');
        let removedCount = 0;

        for (const cookie of cookies) {
            const [cookieName] = cookie.split('=').map(item => item.trim());

            // Check if this cookie matches any of our patterns
            const shouldRemove = regexPatterns.some(regex => regex.test(cookieName));

            if (shouldRemove) {
                // Try multiple domain variations to ensure removal
                const domains = [
                    '', // current domain
                    location.hostname,
                    '.' + location.hostname, // with leading dot
                    location.hostname.replace(/^www\./, ''), // without www
                    '.' + location.hostname.replace(/^www\./, '') // without www, with leading dot
                ];

                const paths = ['/', ''];

                // Try all combinations of domain and path
                for (const domain of domains) {
                    for (const path of paths) {
                        const domainPart = domain ? `domain=${domain};` : '';
                        const pathPart = `path=${path};`;
                        const removalString = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; ${pathPart} ${domainPart}`;

                        // Use original setter directly to bypass our own blocker
                        originalSetter.call(document, removalString);
                    }
                }

                removedCount++;
            }
        }

        if (removedCount > 0 && enableLogging) {
            console.log(`Removed ${removedCount} blocked cookies using direct method`);
        }
    }

    // Run cleanup when the script loads
    cleanBlockedCookies();

    // Also run cleanup periodically to catch cookies set after our script loads
    setInterval(cleanBlockedCookies, 5000);
})();