<?php

//declare(strict_types=1);

namespace cryodrift\uploader;

use Exception;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\Request;
use cryodrift\fw\Response;
use cryodrift\fw\trait\WebHandler;

class Api implements Handler
{

    use WebHandler;

    public string $uploaddir;
    protected string $chunkdir;
    public string $trashdir;
    const string EVENT_UPLOAD = 'uploader.upload';

    public function __construct(Context $ctx, string $storagedir, protected array $getvar_defaults)
    {
        $dir = $storagedir . $ctx->user() . '/';
        $this->uploaddir = $dir . 'uploads/';
        $this->chunkdir = $dir . 'chunks/';
    }

    public function handle(Context $ctx): Context
    {
        return $this->handleWeb($ctx);
    }

    /**
     * @web handle upload of files
     */
    public function upload(Context $ctx): Context
    {
        if ($ctx->request()->isPost()) {
            $ctx->request()->setDefaultVars($this->getvar_defaults);

            Core::dirCreate($this->uploaddir, false);
            Core::dirCreate($this->chunkdir, false);

            $req = $ctx->request();
            $uploadId = $req->vars('uploadId');
            $uploadId = md5($uploadId);
            $metafile = $this->chunkdir . $uploadId . '.info';
            $chunkIndex = (int)$req->vars('chunk', 0);
            $totalChunks = (int)$req->vars('total', 0);
            $ofilename = $req->vars('filename');
            $filename = Core::cleanFilename($ofilename);
            $finalFile = $this->uploaddir . $filename;
            $override = (bool)$req->vars('override');
            // TODO check filesize
            if (file_exists($finalFile) && !$override) {
//                Core::echo(__METHOD__, 'dont upload');
                $ctx->response()->setData($this->getResponseData($totalChunks, $totalChunks, $uploadId, $req, $filename, $ofilename, true, $override));
                return $ctx;
            }

            if (file_exists($metafile)) {
                $data = file_get_contents($metafile);
                $data = Core::jsonRead($data);
                // skip existing chunks
                $realindex = Core::getValue('chunk', $data, $chunkIndex);

                if ($realindex > $chunkIndex && Core::getValue('success', $data)) {
                    // tell client the starting chunk
//                    Core::echo(__METHOD__, 'found old chunks', $realindex);
                    $ctx->response()->setData($this->getResponseData($realindex, $totalChunks, $uploadId, $req, $filename, $ofilename, $realindex === $totalChunks - 1, $override));
                    return $ctx;
                }
            } elseif ($chunkIndex > 0) {
                // something wrong happend we dont allow uploading random chunks
                $ctx->response()->setData($this->getResponseData($chunkIndex, $totalChunks, $uploadId, $req, $filename, $ofilename, true, $override));
                return $ctx;
            }


            $chunkFile = $this->chunkdir . $uploadId . '_' . $this->getChunkname($chunkIndex);

            $tmpname = Core::getValue('tmp_name', Core::getValue('file', $_FILES, []));
            $infofile = $this->chunkdir . $uploadId . '.info';

            try {
                if ($tmpname && move_uploaded_file($tmpname, $chunkFile)) {
                    if ($chunkIndex === $totalChunks - 1 && $filename) {
                        // Combine all chunks
                        $out = fopen($finalFile, 'wb');
                        $retry = 100;
                        for ($i = 0; $i < $totalChunks; $i++) {
                            $currentchunk = $this->chunkdir . $uploadId . '_' . $this->getChunkname($i);
                            try {
                                if (file_exists($currentchunk)) {
                                    $in = fopen($currentchunk, 'rb');
                                    if ($in !== false) {
                                        stream_copy_to_stream($in, $out);
                                        fclose($in);
                                    } else {
                                        throw new Exception('could not open chunk');
                                    }
                                } else {
                                    throw new Exception('chunk file missing');
                                }
                            } catch (Exception $ex) {
                                if ($retry > 0) {
                                    $i--;
                                    $retry--;
                                    Core::echo(__METHOD__, $ex->getMessage(), $currentchunk, $retry);
                                } else {
                                    throw $ex;
                                }
                            }
                        }
                        fclose($out);
                        if (filesize($finalFile) === (int)$req->vars('totalsize', 0)) {
                            for ($i = 0; $i < $totalChunks; $i++) {
                                unlink($this->chunkdir . $uploadId . '_' . $this->getChunkname($i));
                            }
                            if (file_exists($infofile)) {
                                unlink($infofile);
                            }

                            $ctx->events()->run(self::EVENT_UPLOAD, $finalFile);
                        }
                    }

                    $ctx->response()->setData($this->getResponseData($chunkIndex, $totalChunks, $uploadId, $req, $filename, $ofilename, $chunkIndex === $totalChunks - 1, $override));

                    $data = $ctx->response()->getData();
                    if (Core::getValue('uploaded', $data, false) === false) {
                        Core::fileWrite($infofile, Core::jsonWrite($data));
                    }
                } else {
                    if ($chunkIndex === 0 && !$tmpname) {
                        $ctx->response()->setData($this->getResponseData($chunkIndex, $totalChunks, $uploadId, $req, $filename, $ofilename, $chunkIndex === $totalChunks - 1, $override));
                    } else {
                        throw new Exception('Failed to move uploaded file');
                    }
                }
            } catch (Exception $e) {
                Core::echo(__METHOD__, $e, $_FILES, $tmpname, $chunkFile);
                $ctx->response()->addHeader(Response::HEADER_BAD_REQUEST);
                $ctx->response()->setData(['success' => false, 'error' => $e->getMessage()]);
            }
        }
        return $ctx;
    }


    private function getChunkname(string $name): string
    {
        return str_pad($name, 9, 0, STR_PAD_LEFT);
    }

    private function getResponseData(int $chunkIndex, int $totalChunks, string $uploadId, Request $req, string $filename, string $ofilename, bool $uploaded, bool $override, bool $success = true): array
    {
        return [
          'success' => $success,
          'chunk' => $chunkIndex,
          'uploaded' => $uploaded,
          'hash' => $uploadId,
          'totalsize' => (int)$req->vars('totalsize', 0),
          'override' => $override,
          'name' => $filename,
          'total' => $totalChunks,
          'oname' => $ofilename
        ];
    }
}
