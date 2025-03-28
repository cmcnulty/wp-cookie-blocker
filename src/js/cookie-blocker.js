/**
 * WP Cookie Blocker Script
 * 
 * Blocks cookies based on regex patterns configured in the admin
 */
(function() {
    // Get settings from WordPress
    const patterns = typeof wpCookieBlocker !== 'undefined' && wpCookieBlocker.patterns 
        ? wpCookieBlocker.patterns 
        : ['wp-dark-mode-']; // Default
    
    const enableLogging = typeof wpCookieBlocker !== 'undefined' 
        ? !!wpCookieBlocker.enableLogging 
        : false;
    
    // Compile regex patterns
    const regexPatterns = patterns.map(pattern => {
        try {
            // Check if it's already a regex pattern (starts and ends with /)
            if (pattern.match(/^\/.*\/[gimsuy]*$/)) {
                // Extract pattern and flags
                const match = pattern.match(/^\/(.*)\/([gimsuy]*)$/);
                if (match) {
                    return new RegExp(match[1], match[2]);
                }
            }
            
            // If it has special regex characters but isn't formatted as /pattern/flags
            if (/[[\](){}?*+|^$\\.]/.test(pattern)) {
                // Treat as regular expression without flags
                return new RegExp(pattern);
            }
            
            // Treat as simple prefix
            return new RegExp(`^${pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`);
        } catch (e) {
            if (enableLogging) {
                console.error(`Invalid regex pattern: ${pattern}`, e);
            }
            // Fallback to exact match
            return new RegExp(`^${pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`);
        }
    });
    
    if (enableLogging) {
        console.log('Cookie Blocker active with patterns:', patterns);
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
                // Standard way to remove a cookie
                document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
                removedCount++;
                
                // Try with domain too for stubborn cookies
                if (document.location.hostname) {
                    document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=${document.location.hostname};`;
                    document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=.${document.location.hostname};`;
                }
            }
        }
        
        if (removedCount > 0 && enableLogging) {
            console.log(`Removed ${removedCount} blocked cookies`);
        }
    }
    
    // Run cleanup when the script loads
    cleanBlockedCookies();
    
    // Also run cleanup periodically to catch cookies set after our script loads
    setInterval(cleanBlockedCookies, 5000);
})();
