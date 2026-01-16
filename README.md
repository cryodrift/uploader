# Uploader

Simple uploader API.

## Routes

All routes are provided by methods annotated with `@web` in `src/uploader/Api.php` and are accessible under `/uploader/{method}`. Available routes and parameters:

- POST /uploader/upload — handle chunked file uploads
  - params (multipart/form-data):
    - file (required, binary): the file chunk
    - uploadId (required, string): client upload identifier
    - chunk (required, int): zero-based chunk index
    - total (required, int): total number of chunks
    - filename (required, string): original filename
    - totalsize (required, int): expected total size in bytes
    - override (optional, bool): set true to override an existing file

## CLI

If a CLI is available for this component:
- Show commands:
  php index.php /uploader/cli -help
