/**
 * JSON Reader Module
 * ES6 module for reading and processing JSON data
 * Used in template-home-v2.php as referenced in original code
 */

// Modern JSON reader implementation
class UpraJsonReader {
    constructor() {
        this.cache = new Map();
        this.retryAttempts = 3;
        this.retryDelay = 1000;
    }

    /**
     * Read JSON from URL with caching and retry logic
     */
    async readJson(url, options = {}) {
        const cacheKey = this.getCacheKey(url, options);
        
        // Check cache first
        if (this.cache.has(cacheKey) && !options.bypassCache) {
            return this.cache.get(cacheKey);
        }

        let lastError;
        
        for (let attempt = 1; attempt <= this.retryAttempts; attempt++) {
            try {
                const data = await this.fetchJson(url, options);
                
                // Cache successful response
                this.cache.set(cacheKey, data);
                
                return data;
            } catch (error) {
                lastError = error;
                console.warn(`JSON fetch attempt ${attempt} failed:`, error.message);
                
                // Wait before retry (except on last attempt)
                if (attempt < this.retryAttempts) {
                    await this.delay(this.retryDelay * attempt);
                }
            }
        }
        
        throw new Error(`Failed to fetch JSON after ${this.retryAttempts} attempts: ${lastError.message}`);
    }

    /**
     * Fetch JSON with timeout and error handling
     */
    async fetchJson(url, options = {}) {
        const controller = new AbortController();
        const timeout = options.timeout || 10000;
        
        // Set up timeout
        const timeoutId = setTimeout(() => controller.abort(), timeout);
        
        try {
            const response = await fetch(url, {
                method: options.method || 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                body: options.body ? JSON.stringify(options.body) : null,
                signal: controller.signal,
                ...options.fetchOptions
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            return data;
        } catch (error) {
            clearTimeout(timeoutId);
            
            if (error.name === 'AbortError') {
                throw new Error('Request timeout');
            }
            
            throw error;
        }
    }

    /**
     * Read multiple JSON sources concurrently
     */
    async readMultiple(urls, options = {}) {
        const promises = urls.map(url => 
            this.readJson(url, options).catch(error => ({ error, url }))
        );
        
        const results = await Promise.all(promises);
        
        const successful = [];
        const failed = [];