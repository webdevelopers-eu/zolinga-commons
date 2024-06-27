# Throttling

The `DownloaderService` class has a built-in throttling mechanism to prevent the server from being overloaded with requests. 

To configure per-domain throttling create a [configuration](:Zolinga Core:Configuration) with this syntax:
    
```json
{
    "downloader": {
        "throttle": {
            "HOST_NAME": {
                "max": MAX_REQUESTS,
                "time": PER_TIME_IN_SECONDS
            }
        }
    }
}
```

* `HOST_NAME` is the domain name of the host to throttle. Will match the end of the host name. E.g. "example.com" will match "www.example.com", "sub.sub.example.com" and "example.com".
* `MAX_REQUESTS` is the maximum number of requests allowed in the given time frame.
* `PER_TIME_IN_SECONDS` is the time frame in seconds. 

Example:

```json
{
    "downloader": {
        "throttle": {
            "example.org": {
                "max": 1,
                "time": 10
            },
            "example.com": {
                "max": 1,
                "time": 1
            }
        }
    }
}
```