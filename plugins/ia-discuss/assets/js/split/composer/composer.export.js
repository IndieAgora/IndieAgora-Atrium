"use strict";
            attachments.length = 0;
            attachList.innerHTML = "";
            setOpen(false); // ✅ collapse after submit
          }
        });
      }
    });

    // Safety: if some other script expands it after we bind, re-collapse once
    // (but never fight modal startOpen).
    Promise.resolve().then(() => {
      if (!startOpen && !body.hasAttribute("hidden")) setOpen(false);
      if (startOpen) setOpen(true);
    });
  }

  window.IA_DISCUSS_UI_COMPOSER = { composerHTML, bindComposer };
;
