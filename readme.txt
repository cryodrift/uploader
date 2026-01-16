Events

uploader.upload


uploader must only upload
file tools are in files

    /**
     * @web remove files by id
     */
    protected function remove(Context $ctx): Context
    {
        $out = [[]];
        $req = $ctx->request();
        if ($req->isPost()) {
            $ids = $this->jsonRequestHelperColumn($req, 'id', 'id');

                foreach ($ids as $id) {
                    $uid = Core::pop(explode('_', $id, 3));
                    $file = Core::pop($this->fileslist($uid));
                    $filename = Core::getValue('name', $file);
                    if ($filename) {
                        unlink(Core::getValue('realpath', $value));
                        if (!file_exists(Core::getValue('realpath', $value))) {
                            $out[] = ['id' => $id];
                            $ctx->events()->run(self::EVENT_MOVE, ['uid' => $uid, 'source' => Core::getValue('realpath', $file), 'dest' => $destpathname]);
                        }

                    }
                    Core::echo(__METHOD__, $uid, $destpathname, $file);
                }
        }
        $ctx->response()->setData($out);
        return $ctx;
    }


    /**
     * @web show list of dirs
     */
    public function dir_list(Context $ctx, string $selected = ''): HtmlUi
    {
        $dirs = Core::dirList($this->uploaddir, fn(\SplFileInfo $f) => $f->isDir() && $f->getFilename() !== '..', 0);
        $ui = HtmlUi::fromString('<option value="{{value}}" {{selected}}>{{value}}</option>', 'options');
        $options = [];

        foreach ($dirs as $dir) {
            $val = '/' . trim(str_replace('\\', '/', str_replace(trim($this->uploaddir, '/'), '', $dir->getPath())), '/');

            if ($val) {
                $opt = ['value' => $val];
                if ($selected && $val === $selected) {
                    $opt['selected'] = 'selected=selected';
                } else {
                    $opt['selected'] = '';
                }
                $options[] = $opt;
            }
        }
        $ui->setAttributes(['options' => $options]);
        return $ui;
    }

    /**
     * @web show list of uploaded files
     */
    public function files_list(Context $ctx, string $search = '', string $path = ''): HtmlUi
    {
        $files = $this->fileslist($search, $path);
        $ui = HtmlUi::fromFile(__DIR__ . '/files.html', 'files')->fromBlock('files')->setAttributes(['uploaded_block' => $files]);
        return $ui;
    }

    private function fileslist(string $search = '', string $path = ''): array
    {
        $files = [];
        $uploaddir = trim($this->uploaddir . $path, '/');
        Core::echo(__METHOD__, $uploaddir);
        foreach (Core::dirList($uploaddir) as $file) {
            $fobj = new \SplFileObject($file->getPathname());
            $tmp = [
              'id' => md5($fobj->getRealPath()),
              'uid' => md5_file($fobj->getRealPath()),
              'name' => $fobj->getFilename(),
              'size' => $fobj->getSize(),
              'fext' => $fobj->getExtension(),
              'realpath' => $fobj->getRealPath(),
              'path' => $fobj->getPath(),
              'shortpath' => '/' . trim(str_replace($uploaddir, '', $fobj->getPath()), '\\/') . '/',
              'tst' => $fobj->getCTime(),
              'date' => date('Y-m-d H:i:s', $fobj->getCTime()),
            ];
            if (str_contains(strtolower($tmp['shortpath'] . $tmp['name'] . $tmp['date'] . $tmp['size'] . $tmp['uid']), strtolower($search))) {
                $files[] = $tmp;
            }
        }
        return $files;
    }

