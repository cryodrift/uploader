let override = false

/**
 * Restartable chunk uploader
 *
 */
export function init(elem, desturl, progressid, overrideselector, totalprogressid) {
   // console.log('uploader.js', elem, desturl, progressid)
   const uploader = new Uploader(desturl)
   const progresstpl = document.getElementById(progressid).content
   const parent = document.getElementById(progressid).parentElement
   const totalprogress = progresstpl.cloneNode(true).firstElementChild
   document.getElementById(totalprogressid).appendChild(totalprogress)
   const progresses = {}
   const finished = {}
   let filescount

   // Handle file input
   document.querySelector('input[type="file"]').addEventListener('change', async (e) => {
      override = !!document.querySelector(overrideselector);
      filescount = e.target.files.length
      Object.keys(finished).forEach(k => {
         parent.removeChild(finished[k])
         delete finished[k]
      })
      totalprogress.querySelector('label').innerHTML = ' finished 0 from ' + filescount
      await uploader.uploadFiles(e.target.files)
      e.target.value = ''
   })

   // Listen for progress updates
   window.addEventListener('upload-progress', (e) => {
      const uploadId = e.detail.uploadId
      if (!progresses[uploadId]) {
         const clone = progresstpl.cloneNode(true)
         progresses[uploadId] = clone.firstElementChild
         parent.prepend(clone)
         progresses[uploadId].querySelector('progress').addEventListener('click', () => uploader.pauseUpload(uploadId))
      }
      progresses[uploadId].querySelector('label').innerHTML = ' ' + (e.detail.size / 1024).toFixed(2) + ' KB ,' + e.detail.name
      progresses[uploadId].querySelector('progress').value = e.detail.progress

      if (e.detail.progress === 100) {
         finished[uploadId] = progresses[uploadId]
         delete progresses[uploadId]
         const fl = Object.keys(finished).length
         totalprogress.querySelector('label').innerHTML = ' finished ' + fl + ' from ' + filescount
         const progressvalue = ((fl / filescount) * 100).toFixed(0)
         totalprogress.querySelector('progress').value = progressvalue
      }
   })
}

class Uploader {
   constructor(endpoint) {
      this.endpoint = endpoint;
      this.chunkSize = 1024 * 1024;
      this.activeUploads = new Map()
      this.__CLASS__ = 'uploader.js'
   }

   #timeouts = {}

   async uploadFiles(files) {
      for (const file of files) {
         const uploadId = file.name + '_' + file.size
         if (!this.activeUploads.has(uploadId) || this.activeUploads.get(uploadId).progress === 100) {
            this.activeUploads.set(uploadId, {progress: 0, paused: false, file: file, chunksdone: 0})
            await this.uploadFile(uploadId)
         }
      }
   }

   async uploadFile(uploadId) {
      const file = this.activeUploads.get(uploadId).file
      const chunks = Math.ceil(file.size / this.chunkSize)
      let uploadchunk = false
      try {

         let uploadedChunks = this.activeUploads.get(uploadId).chunksdone;

         while (uploadedChunks < chunks && !this.activeUploads.get(uploadId).paused) {
            const start = uploadedChunks * this.chunkSize
            const end = Math.min(start + this.chunkSize, file.size)
            const chunk = file.slice(start, end)

            const formData = new FormData()
            if (uploadchunk) formData.append('file', chunk)
            formData.append('uploadId', uploadId)
            formData.append('chunk', uploadedChunks)
            formData.append('total', chunks)
            formData.append('filename', file.name)
            formData.append('totalsize', file.size)
            formData.append('override', override)

            try {
               const response = await fetch(this.endpoint, {
                  method: 'POST',
                  body: formData
               })
               if (response.ok) {
                  const responsecopy = response.clone()
                  try {
                     const resdata = await response.json()
                     uploadedChunks = parseInt(resdata.chunk)
                     if (uploadchunk) uploadedChunks++;
                     else {
                        uploadchunk = true
                     }
                     uploadedChunks = Math.min(uploadedChunks, chunks)
                     const progress = (uploadedChunks / chunks) * 100;
                     this.updateProgress(uploadId, progress, file.name, file.size)
                     this.activeUploads.get(uploadId).chunksdone = uploadedChunks
                  } catch (e) {
                     const rtext = await responsecopy.text()
                     console.log(this.__CLASS__, e, rtext)
                     if (!rtext) {
                        this.activeUploads.get(uploadId).paused = true;
                        return;
                     }

                  }
               } else {
                  console.log(this.__CLASS__, 'Response not ok', response)
                  this.pauseUpload(uploadId)
               }
            } catch (e) {
               console.log(this.__CLASS__, 'fetch failed', e)
               this.pauseUpload(uploadId)
            }
         }
      } catch (error) {
         console.log(this.__CLASS__, 'Upload error', error);
         this.pauseUpload(uploadId)
      }
   }

   updateProgress(uploadId, progress, name, size, filescount) {
      this.activeUploads.get(uploadId).progress = progress;
      // Dispatch progress event
      window.dispatchEvent(new CustomEvent('upload-progress', {
         detail: {uploadId, progress, name, size, filescount}
      }));
   }

   pauseUpload(uploadId, uploadedChunks) {
      const upload = this.activeUploads.get(uploadId)
      if (upload) {
         upload.paused = true;
         upload.uploadedChunks = uploadedChunks;
         if (this.#timeouts[uploadId]) clearTimeout(this.#timeouts[uploadId])
         this.#timeouts[uploadId] = setTimeout(() => {
            delete this.#timeouts[uploadId]
            this.resumeUpload(uploadId)

         }, 3000)
      }
   }

   resumeUpload(uploadId) {
      const upload = this.activeUploads.get(uploadId)
      if (upload && upload.paused) {
         upload.paused = false;
         this.uploadFile(uploadId)
      }
   }
}

