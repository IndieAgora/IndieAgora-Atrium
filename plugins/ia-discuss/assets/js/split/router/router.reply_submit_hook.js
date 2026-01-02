"use strict";
          }).then((res) => {
            if (!res || !res.success) {
              ui.setError((res && res.data && res.data.message) ? res.data.message : "Reply failed");
              return;
            }

            var postId = res.data && res.data.post_id ? parseInt(res.data.post_id, 10) : 0;

            window.dispatchEvent(new CustomEvent("iad:close_composer_modal"));

            openTopicPage(d.topic_id, {
              scroll_post_id: postId || 0,
              highlight_new: 1
            });
          });
        }
      });
    });

    // initial view
    render("new", 0, "");
  }

  window.IA_DISCUSS_ROUTER = { mount };
;
