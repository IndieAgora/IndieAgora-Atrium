"use strict";
      if (quoteBtn) {
        var author = quoteBtn.getAttribute("data-quote-author") || "";
        var postEl = quoteBtn.closest(".iad-post");
        var text = extractQuoteTextFromPost(postEl);
        var quote = `[quote]${author ? author + " wrote:\n" : ""}${text}[/quote]\n\n`;
        openReplyModal(topicId, quote);
        return;
      }

      if (replyBtn) {
        openReplyModal(topicId, "");
        return;
      }
    });
  }

  window.IA_DISCUSS_TOPIC_ACTIONS = { bindTopicActions };
;
