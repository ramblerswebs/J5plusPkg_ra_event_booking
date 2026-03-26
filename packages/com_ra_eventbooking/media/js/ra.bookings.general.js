/* 
 * copyright: Chris Vaughan
 * email: ruby.tuesday@ramblers-webs.org.uk
 * 
 * EW     an RA event or walk in ramblers library format
 * ESC    a collection of booking records , EVB
 * EVB    a booking record for an event,  an object
 * NBI    a new booking information for one user
 * BLC    a collection of bookings, collection of BLI
 * BLI    the user information booking for a user
 * WLC    a collection of waiting records, collection of WLI
 * WLI    the user information about someone on waiting list
 */
var ra;
if (typeof (ra) === "undefined") {
    ra = {};
}

if (typeof (ra.bookings) === "undefined") {
    ra.bookings = {};
}

// general functions/classes

ra.bookings.queryServer = function (self, action) {
    this.url = null;
    this.serverProgressModal = null;
    this.progressMsg = null;
    switch (action) {
        case 'getEventsSummary':
            this.url = "index.php?option=com_ra_eventbooking&view=getallbookings&format=json";
            break;
        case 'getSingleEvent':
            this.url = "index.php?option=com_ra_eventbooking&view=getbookingstatus&format=json";
            break;
        case 'DisableEvent':
            progressMsg = 'Disabling booking for event ...';
            this.url = "index.php?option=com_ra_eventbooking&view=disableevent&format=json";
            break;
        case 'SubmitBooking':
            this.progressMsg = 'Submitting your booking ...';
            this.url = "index.php?option=com_ra_eventbooking&view=submitbooking&format=json";
            break;
        case 'CancelBooking':
            this.progressMsg = 'Cancel your booking ...';
            this.url = "index.php?option=com_ra_eventbooking&view=submitbooking&format=json";
            break;
        case 'Waiting':
            this.progressMsg = 'Changing your waiting list setting ...';
            this.url = "index.php?option=com_ra_eventbooking&view=waiting&format=json";
            break;
        case 'EventChanged':
            this.url = "index.php?option=com_ra_eventbooking&view=eventchanged&format=json";
            break;
        case 'AdminEmailAllBooking':
            this.url = "index.php?option=com_ra_eventbooking&view=adminemailallbook&format=json";
            break;
        case 'Adminemailsinglebooking':
            this.progressMsg = 'Sending email ...';
            this.url = "index.php?option=com_ra_eventbooking&view=adminemailsinglebook&format=json";
            break;
        case 'AdminDeleteSingleBooking':
            this.progressMsg = 'Deleting a single booking ...';
            this.url = "index.php?option=com_ra_eventbooking&view=admindeletesinglebook&format=json";
            break;
        case 'AdminEmailAllWaiting':
            this.progressMsg = 'Sending email to all on notify list ...';
            this.url = "index.php?option=com_ra_eventbooking&view=adminemailallwait&format=json";
            break;
        case 'AdminEmailSingleWaiting':
            this.progressMsg = 'Sending email ...';
            this.url = "index.php?option=com_ra_eventbooking&view=adminemailsinglewait&format=json";
            break;
        case 'AdminDeleteSingleWaiting':
            this.progressMsg = 'Removing notify status ...';
            this.url = "index.php?option=com_ra_eventbooking&view=admindeletesinglewait&format=json";
            break;
        case'AdminChangePaid':
            this.progressMsg = 'Changing payment status ...';
            this.url = "index.php?option=com_ra_eventbooking&view=adminchangepaid&format=json";
            break;
        case'AdminEmailBookingList':
            this.progressMsg = 'Sending email with booking list ...';
            this.url = "index.php?option=com_ra_eventbooking&view=adminemailbookinglist&format=json";
            break;
        case 'VerifyEmail':
            this.progressMsg = 'Sending verification email ...';
            this.url = "index.php?option=com_ra_eventbooking&view=verifyemail&format=json";
            break;
        case 'NotifyListEmail':
            this.url = "index.php?option=com_ra_eventbooking&view=notifylistemail&format=json";
            break;
        case 'emailBookingContact':
            this.progressMsg = 'Sending email to booking contact';
            this.url = "index.php?option=com_ra_eventbooking&view=emailbookingcontact&format=json";
            break;
        default:
            ra.showMsg(action);
            return;
    }
    this.url = ra.baseDirectory() + this.url;

    this.action = function (dataObj, fcn) {
        if (this.progressMsg !== null) {
            this.serverProgressModal = this.displayProgress(this.progressMsg);
            this.serverProgressModal.hideClose();
        } // if progressMsg is null then action is silent and no feedback parameter is expected

        if (dataObj === null) {
            dataObj = {noInput: true};
        }
        var _this = this;
        var formData = new FormData();
        formData.append("data", JSON.stringify(dataObj));
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.onload = function () {
            if (xmlhttp.readyState === 4) {
                var response = {};
                try {
                    response = JSON.parse(xmlhttp.responseText);
                    response.status = xmlhttp.status;
                    if (response.status === 200) {
                        _this.displayFeedback(response.data.feedback);
                        fcn(self, response);
                    } else {
                        _this.displayFeedbackErr('Whoops - something went wrong [' + action + ']: ' + response.message);
                    }
                } catch (er) {
                    _this.displayFeedbackErr('Invalid reply from server, if this continues please contact us');
                }
            }
        };
        xmlhttp.open("POST", this.url, true);
        xmlhttp.send(formData);
    };
    this.displayProgress = function (msg) {
        if (msg === null) {
            ra.showMsg('Program error: invalid progress msg');
        }
        var div = document.createElement("div");
        div.classList.add('ra');
        div.classList.add('booking');
        div.classList.add('feedback');
        div.style.display = "inline-block";
        var modal = ra.modals.createModal(div, false);
        var div1 = document.createElement("div");
        div1.innerHTML = '<h3>' + msg + '</h3>';
        div.appendChild(div1);
        return modal;
    };
    this.displayFeedback = function (feedback) {
        if (feedback === null) {
            ra.showMsg('Invalid response from server, feedback is null');
            return;
        }
        if (feedback.length < 1) {
            return;
        }
        if (this.serverProgressModal === null) {
            ra.showMsg('Program error: no progress modal');
            return;
        }
        this.serverProgressModal.showClose();

        if (typeof feedback === 'string') {
            console.log('feedback' + feedback);
            this.serverProgressModal.resetContent(feedback);
            return;
        }
        // array
        feedback.forEach(item => {
            console.log('feedback' + item);
            var div1 = document.createElement("div");
            div1.innerHTML = item;
            // needs fixing for more than one item
            this.serverProgressModal.resetContent(div1);
        });

    };
    this.displayFeedbackErr = function (feedback) {
        if (this.serverProgressModal === null) {
            ra.showMsg(feedback);
            return;
        }
        this.serverProgressModal.showClose();
        this.serverProgressModal.resetContent(feedback);
    };
};


ra.bookings.addTextTag = function (tag, ele = 'div', text = '') {
    var ele = document.createElement(ele);
    ele.innerHTML = text;
    tag.appendChild(ele);
};


ra.bookings.displayEmailIcon = function (tag, desc, eventTag, eventName, data = null) {
    var span = document.createElement("span");
    span.classList.add('ra', 'bookings', 'envelope');
    span.setAttribute("title", desc);
    tag.appendChild(span);
    span.addEventListener('click', (e) => {
        let event = new Event(eventName);
        event.raData = data;
        eventTag.dispatchEvent(event);
    });
};
ra.bookings.displayDeleteIcon = function (tag, desc, eventTag, eventName, data = null) {
    var span = document.createElement("span");
    span.classList.add('ra', 'bookings', 'delete');
    span.setAttribute("title", desc);
    tag.appendChild(span);
    span.addEventListener('click', (e) => {
        let event = new Event(eventName);
        event.raData = data;
        eventTag.dispatchEvent(event);
    });
};
ra.bookings.inputFields = function () {
    this.addHeader = function (tag, headTag, label, helpFunction = null) {
        var heading = document.createElement(headTag);
        heading.innerHTML = label;
        heading.title = 'Click to open or close section';
        tag.appendChild(heading);
        if (helpFunction !== null) {
            new ra.help(heading, helpFunction).add();
        }
        return heading;
    };
    this.addText = function (tag, divClass, label, raobject, property, placeholder = '', helpFunction = null) {
        var itemDiv = document.createElement('div');
        itemDiv.setAttribute('class', divClass);
        tag.appendChild(itemDiv);
        var _label = document.createElement('label');
        _label.setAttribute('class', 'booking label');
        _label.textContent = label;
        var inputTag = document.createElement('input');
        inputTag.setAttribute('class', 'booking input');
        inputTag.setAttribute('type', 'text');
        inputTag.setAttribute('placeholder', placeholder);
        inputTag.raobject = raobject;
        inputTag.raproperty = property;
        if (raobject.hasOwnProperty(property)) {  // Initialise value
            inputTag.value = raobject[property];
        }
        inputTag.addEventListener("input", function (e) {
            e.target.raobject[e.target.raproperty] = e.target.value;
        });
        itemDiv.appendChild(_label);
        itemDiv.appendChild(inputTag);
        if (helpFunction !== null) {
            new ra.help(itemDiv, helpFunction).add();
        }
        return inputTag;
    };
    this.addNumber = function (tag, divClass, label, raobject, property, helpFunction = null) {
        var _label = document.createElement('label');
        _label.setAttribute('class', 'booking label');
        _label.textContent = label;
        var inputTag = document.createElement('input');
        inputTag.setAttribute('class', 'booking input');
        inputTag.setAttribute('type', 'text');
        inputTag.raobject = raobject;
        inputTag.raproperty = property;
        if (raobject.hasOwnProperty(property)) {  // Initialise value
            inputTag.value = raobject[property];
        }
        inputTag.addEventListener("input", function (e) {
            e.target.raobject[e.target.raproperty] = e.target.value;
        });
        tag.appendChild(_label);
        tag.appendChild(inputTag);
        if (helpFunction !== null) {
            new ra.help(_label, helpFunction).add();
        }

        //   var inputTag = this.addText(tag, divClass, label, raobject, property, '', helpFunction);
        inputTag.setAttribute('type', 'number');
        inputTag.setAttribute('step', '.01');
        return inputTag;
    };
    this.addNumberSelect = function (tag, divClass, label, raobject, property, range, helpFunction = null) {
        var _label = document.createElement('label');
        _label.setAttribute('class', 'booking label');
        _label.textContent = label;
        var inputTag = document.createElement('select');
        var first = true;
        var no = 0;
        for (let i = range.min; i < range.max + 1; i++) {
            if (i === range.current) {
                continue;
            }
            var opt = document.createElement('option');
            opt.value = i;
            no += 1;
            if (i === 1) {
                opt.innerHTML = i.toString() + " attendee";
            } else {
                opt.innerHTML = i.toString() + " attendees";
            }
            if (first) {
                first = false;
                inputTag.value = i;
                raobject[property] = i;
            }
            inputTag.appendChild(opt);
        }
        if (no === 0) {
            return null;
        }
        inputTag.setAttribute('class', 'booking input');
        inputTag.raobject = raobject;
        inputTag.raproperty = property;
        inputTag.addEventListener("input", function (e) {
            e.target.raobject[e.target.raproperty] = e.target.value;
        });
        tag.appendChild(_label);
        tag.appendChild(document.createElement('br'));
        tag.appendChild(inputTag);
        if (helpFunction !== null) {
            new ra.help(_label, helpFunction).add();
        }
        return inputTag;
    };
    this.addSelect = function (tag, divClass, label, values, raobject, property, helpFunction = null) {
        var _label = document.createElement('label');
        _label.setAttribute('class', 'booking label');
        _label.textContent = label;
        var inputTag = document.createElement('select');
        var no = 0;
        for (var key in values) {
            no += 1;
        }
        if (no > 1) {
            var option = document.createElement("option");
            option.value = "undefined";
            option.text = "Please Select...";
            inputTag.appendChild(option);
        }
        for (var key in values) {
            var value = values[key];
            var option = document.createElement("option");
            option.value = key;
            option.text = value;
            if (no === 1) {
                raobject[property] = key;
            }
            inputTag.appendChild(option);
        }
        inputTag.setAttribute('class', 'booking input');
        inputTag.raobject = raobject;
        inputTag.raproperty = property;
        inputTag.addEventListener("input", function (e) {
            e.target.raobject[e.target.raproperty] = e.target.value;
        });
        tag.appendChild(_label);
        //  tag.appendChild(document.createElement('br'));
        tag.appendChild(inputTag);
        if (helpFunction !== null) {
            new ra.help(_label, helpFunction).add();
        }
        return inputTag;
    };
    this.addEmail = function (tag, divClass, label, raobject, property, placeholder = '', helpFunction = null) {
        var inputTag = this.addText(tag, divClass, label, raobject, property, placeholder, helpFunction);
        inputTag.setAttribute('type', 'email');
        inputTag.addEventListener("input", function (e) {
            e.target.value = e.target.value.toLowerCase();
        });
        return inputTag;
    };
    this.addComment = function (tag, divClass, label, comment, helpFunction = null) {
        var itemDiv = document.createElement('div');
        itemDiv.setAttribute('class', divClass);
        tag.appendChild(itemDiv);
        if (label !== '') {
            var _label = document.createElement('label');
            _label.setAttribute('class', 'booking label');
            _label.textContent = label;
            itemDiv.appendChild(_label);
        }
        var inputTag = document.createElement('span');
        inputTag.setAttribute('class', 'booking input');
        inputTag.textContent = comment;
        itemDiv.appendChild(inputTag);
        if (helpFunction !== null) {
            new ra.help(itemDiv, helpFunction).add();
        }
        return inputTag;
    };
    this.addButton = function (tag, divClass, label, helpFunction = null) {
        var button = document.createElement('span');
        button.innerHTML = label;
        if (divClass !== null) {
            divClass.forEach(c => {
                button.classList.add(c);
            });
        }
        tag.appendChild(button);
        if (helpFunction !== null) {
            new ra.help(button, helpFunction).add();
        }
        return button;
    };
    this.addHtmlArea = function (tag, divClass, label, rows, raobject, property, placeholder = '', helpFunction = null) {
        var itemDiv = document.createElement('div');
        itemDiv.setAttribute('class', divClass);
        tag.appendChild(itemDiv);
        var _label = document.createElement('label');
        _label.setAttribute('class', ' booking');
        _label.textContent = label;
        itemDiv.appendChild(_label);
        if (helpFunction !== null) {
            new ra.help(itemDiv, helpFunction).add();
        }
        var container = document.createElement('div');
        itemDiv.appendChild(container);
        container.setAttribute('class', 'booking quill');
        var inputTag = document.createElement('div');
        container.appendChild(inputTag);
        inputTag.style.width = '95%';
        inputTag.raobject = raobject;
        inputTag.raproperty = property;
        if (raobject.hasOwnProperty(property)) {  // Initialise value
            inputTag.innerHTML = raobject[property];
        }
        var quill = this.addQuill(inputTag);
        quill.on('text-change', function (delta, oldDelta, source) {
            raobject[property] = quill.getSemanticHTML();
        });
        quill.clipboard.addMatcher(Node.ELEMENT_NODE, function (node, delta) {
            var plaintext = node.innerText;
            var Delta = Quill.import('delta');
            return new Delta().insert(plaintext);
        });
        return inputTag;
    };
    this.addQuill = function (container) {
        var toolbarOptions = [[{'header': [false, 1, 2, 3]}],
            ['bold', 'italic', 'underline', 'strike', 'link'],
            [{'list': 'ordered'}, {'list': 'bullet'}]
        ];
        var quill = new Quill(container, {
            theme: 'snow',
            modules: {
                toolbar: toolbarOptions
            }
        });
        return quill;
    };
};



//////////////////////////////////////////////////////////////////////

ra.bookings.user = function (user) {
    this.canEdit = user.canEdit;
    this.md5Email = user.email;
    this.id = user.id;
    this.name = user.name;
};
ra.bookings.defaults = function (defaults) {
    this.attendeetype = defaults.attendeetype;
    this.booking_contact_id = defaults.booking_contact_id;
    this.booking_contact_name = defaults.booking_contact_name;
    this.closingoption = defaults.closingoption;
    this.customclosingdate = defaults.customclosingdate;
    this.guest = defaults.guest;
    this.maxattendees = defaults.maxattendees;
    this.maxguestattendees = defaults.maxguestattendees;
    this.payment_required = defaults.payment_required;
    this.payment_details = defaults.payment_details;
    this.telephone_required = defaults.telephone_required;
    this.total_places = defaults.total_places;
    this.userlistvisibletoguests = defaults.userlistvisibletoguests;
    this.userlistvisibletousers = defaults.userlistvisibletousers;
    this.waitinglist = defaults.waitinglist;
    this.display = function (tag) {
        var tags = [
            {parent: 'root', tag: 'h3', innerHTML: 'Global default settings'},
            {name: 'general', parent: 'root', tag: 'ul'},
            {parent: 'general', tag: 'li', innerHTML: 'Default Booking contact'},
            {name: 'contact', parent: 'general', tag: 'ul'},
            {name: 'type', parent: 'general', tag: 'li'},
            {name: 'total', parent: 'general', tag: 'li'},
            {name: 'pay', parent: 'general', tag: 'li'},
            {name: 't1', parent: 'general', tag: 'li', innerHTML: 'Logged in users'},
            {name: 'list', parent: 'general', tag: 'ul'},
            {name: 't2', parent: 'general', tag: 'li', innerHTML: 'Guest users'},
            {name: 'guestlist', parent: 'general', tag: 'ul'},
            {name: 't3', parent: 'general', tag: 'li', innerHTML: 'Default closing time for bookings'},
            {name: 'closing', parent: 't3', tag: 'ul'}
        ];
        var elements = ra.html.generateTags(tag, tags);
        if (this.booking_contact_name !== '') {
            this.addLi(elements.contact, this.booking_contact_name);
        } else {
            this.addLi(elements.contact, "No booking contact defined");
        }
        switch (this.attendeetype) {
            case 'public':
                elements.type.innerHTML = 'Events are open to the public';
                break;
            case 'memonly':
                elements.type.innerHTML = 'Events are only for Ramblers members';
                break;
            default:
                elements.type.innerHTML = 'ERROR attendee type is not valid';
        }
        if (this.payment_required) {
            elements.pay.innerHTML = 'Payment required';
        } else {
            elements.pay.innerHTML = "No Payment required";
        }
        elements.total.innerHTML = "Number of places for an event is " + this.total_places;
        this.addLi(elements.list, 'Max places that can be booked: ' + this.maxattendees);
        if (this.guest) {
            this.addLi(elements.guestlist, "Guest may book places");
            this.addLi(elements.guestlist, "Max places that can be booked: " + this.maxguestattendees);
        } else {
            this.addLi(elements.guestlist, "Only logged on users may book places");
        }
        if (this.userlistvisibletousers) {
            this.addLi(elements.list, "Logged on users can see who has booked places");
        } else {
            this.addLi(elements.list, "Logged on users <b>cannot</b> see who has booked places");
        }
        if (this.userlistvisibletoguests) {
            this.addLi(elements.guestlist, "Guest users can see who has booked places");
        } else {
            this.addLi(elements.guestlist, "Guest users <b>cannot</b> see who has booked places");
        }
        if (this.telephone_required) {
            this.addLi(elements.guestlist, "Telephone number is mandatory");
        } else {
            this.addLi(elements.guestlist, "Telephone number is optional");
        }
        this.addLi(elements.closing, this.getClosingDescription(this.closingoption));

        if (this.waitinglist) {
            this.addLi(elements.general, "Notify list is allowed");
        } else {
            this.addLi(elements.general, "Notify list is not allowed");
        }
    };
    this.addLi = function (tag, text) {
        var ele = document.createElement('li');
        ele.innerHTML = text;
        tag.appendChild(ele);
    };
    this.getClosingDescription = function (type) {
        switch (type) {
            case 'start':
                return "event start time";
            case '6pm':
                return "6pm on the day before event";
            case '6pmweek':
                return "6pm a week before event";
            case '7am':
                return"7am on the day";
            case '7amweek':
                return"7am a week before";
            case 'custom':
                return "At a custom date and time";
            default:
                return "INVALID SETTING";
        }
    };
    this.overrides = function (options) {
        var out = [];

        if (this.booking_contact_id !== options.booking_contact_id) {
            out[out.length] = 'Booking contact ' + options.booking_contact_name;
        }
        if (this.attendeetype !== options.attendeetype) {
            switch (options.attendeetype) {
                case 'public':
                    out[out.length] = 'Event is open to the public';
                    break;
                case 'memonly':
                    out[out.length] = 'Event is only for Ramblers members';
                    break;
                default:
                    out[out.length] = 'ERROR attendee type is not valid';
            }
        }
        if (this.total_places !== options.total_places) {
            out[out.length] = "Number of places for event is " + options.total_places;
        }


        if (this.closingoption !== options.closingoption) {
            out[out.length] = this.getClosingDescription(options.closingoption);
        }

        if (this.payment_required !== options.payment_required) {
            out[out.length] = options.payment_required ? "Payment required" : "No Payment required";
        }
        if (this.maxattendees !== options.maxattendees) {
            out[out.length] = 'Users: Max places that can be booked: ' + options.maxattendees;
        }

        if (this.userlistvisibletousers !== options.userlistvisibletousers) {
            out[out.length] = options.userlistvisibletousers ? "Users can view who has booked" : "Users CANNOT view who has booked";
        }
        if (this.guest !== options.guest) {
            out[out.length] = options.guest ? "Guest may book places" : "Guest cannot book places";
        }
        if (this.maxguestattendees !== options.maxguestattendees) {
            out[out.length] = 'Guest can book ' + options.maxguestattendees + ' places';
        }
        if (this.userlistvisibletoguests !== options.userlistvisibletoguests) {
            out[out.length] = options.userlistvisibletoguests ? "Guests can view who has booked" : "Guests CANNOT view who has booked";
        }
        if (this.telephone_required !== options.telephone_required) {
            out[out.length] = options.telephone_required ? "Telephone number is mandatory" : "Telephone number is optional";
        }

        if (this.waitinglist !== options.waitinglist) {
            out[out.length] = options.waitinglist ? "Notify list is allowed" : "Notify list is not allowed";
        }
        return out;
    };
};
// Event Summary Collection
ra.bookings.esc = function () {
    this.items = [];
    this.addItem = function (item) {
        this.items.push(item);
    };
    this.process = function (events) {
        events.forEach(event => {
            this.addItem(new ra.bookings.evb(event));
        });
    };
    this.removeItem = function (ewid) {
        let noEwid = this.items.filter(el => el.ewid !== ewid);
        this.items = noEwid;
    };
    // display summary table
    this.displaySummary = function (tag, canEdit = false) {
        if (this.items.length === 0) {
            var h = document.createElement("h4");
            h.innerHTML = 'No events with bookings were found';
            tag.appendChild(h);
            return;
        }
        var format = [{"title": "ID", "options": {align: "left"}, field: {type: 'text', filter: false, sort: true}},
            {"title": "Date", "options": {align: "left"}, field: {type: 'date', filter: false, sort: true}},
            {"title": "Title", "options": {align: "left"}, field: {type: 'text', filter: false, sort: true}},
            {"title": "Places/Booked/Wait", "options": {align: "left"}, field: {type: 'text'}},
            {"title": "Overrides", "options": {align: "left"}, field: {type: 'text'}},
            {"title": "Disable", "options": {align: "right"}}];
        var title = document.createElement("h3");
        title.textContent = "Events/walks with a booking record";
        tag.appendChild(title);
        var table = new ra.paginatedTable(tag);
        table.tableHeading(format);
        this.items.forEach(event => {
            event.displaySummary(table, format, canEdit);
        });
        table.tableEnd();
    };
    this.setOverrides = function (defaults) {
        this.items.forEach(item => {
            item.setOverrides(defaults);
        });
    };
};
// Booking information for an RA event
ra.bookings.evb = function (value) {
    this.ewid = value.event_id;
    this.blc = new ra.bookings.blc();
    this.blc.process(value.blc);
    this.wlc = new ra.bookings.wlc();
    this.wlc.process(value.wlc);
    this.event_data = value.event_data;
    this.event = ra.walk.getEventID(this.ewid);
    this.options = value.options;
    this.actualClosingDate = value.actualClosingDate;
    this.overrides = [];
// Display summary table row
    this.displaySummary = function (table, format, canEdit) {

        table.tableRowStart();
        table.tableRowItem(this.ewid, format[0]);
        if (this.event !== null) {
            var date = this.event.getEventValue('{dowShortddmm}');
            var sortDate = this.event.basics.walkDate;
            var title = this.event.getEventValue('{title}');
            table.tableRowItem(date, format[1], sortDate);
            var ele = table.tableRowItem(title, format[2]);
            ele.setAttribute('data-eventid', this.ewid);
            ele.classList.add('link-button', 'tiny', 'button', 'mintcake');
            ele.addEventListener("click", function (e) {
                var id = e.currentTarget.getAttribute('data-eventid');
                ra.walk.displayWalkID(e, id);
            });
        } else {
            table.tableRowItem('', format[1]);
            table.tableRowItem('Event not found', format[2]);
        }
        var places = this.options.total_places;
        if (places === 0) {
            places = 'Unlimited';
        }
        var out = places + '/' + this.blc.noAttendees() + '/' + this.wlc.noWaiting();
        table.tableRowItem(out, format[3]);
        table.tableRowItem(this.overrides.join("<br>"), format[4]);

        if (canEdit && this.event === null) {
            var disable = table.tableRowItem('Disable', format[5]);
            disable.setAttribute('data-eventid', this.ewid);
            disable.classList.add('link-button', 'tiny', 'button', 'mintcake');
            var self = this;
            disable.addEventListener("click", function (e) {
                var data = {
                    ewid: e.currentTarget.getAttribute('data-eventid')};
                var sa = new ra.bookings.queryServer(self, 'DisableEvent');
                sa.action(data, (self, results) => {
                    self._BookingDisableResult(results);
                });
            });

        } else {
            table.tableRowItem('');
        }
        table.tableRowEnd();
    };
    this._BookingDisableResult = function (results) {
        if (results.status !== 200) {
            ra.showMsg('Unable to disable event/booking record');
            return;
        }
        let event = new Event("disableEvent"); // 
        event.raData = {};
        event.raData.ewid = results.data.ewid;
        document.dispatchEvent(event);
    };
    this.calcMaxPlaces = function (user) {
        var totalBooked = this.blc.noAttendees();
        var booking = this.blc.isPresent(user.md5Email);
        var noBooked = 0;
        if (booking !== null) {
            noBooked = booking.attendees;
        }
        var availablePlaces = 9999;
        if (this.options.total_places > 0) {
            availablePlaces = this.options.total_places - totalBooked + noBooked;
        }

        var maxAttendees;
        if (user.id > 0) {
            maxAttendees = this.options.maxattendees;
        } else {
            maxAttendees = this.options.maxguestattendees;
        }
        if (availablePlaces < maxAttendees) {
            maxAttendees = availablePlaces;
        }
        return maxAttendees;
    };
    this.isFullyBooked = function () {
        var no = this.blc.noAttendees();
        return no >= this.options.total_places && this.options.total_places !== 0;
    };
    this.listAttendees = function (tag, user) {
        if (this.canDisplayBookingList(user)) {
            var options = {
                guest: this.options.guest,
                canEdit: user.canEdit,
                displayPaid: this.options.payment_required && user.canEdit};
            this.blc.list(tag, options);
        }
    };
    this.listWaiting = function (tag, user) {
        if (this.canDisplayWaitingList) {
            var options = {
                guest: this.options.guest,
                canEdit: user.canEdit};
            this.wlc.list(tag, options);
        }

    };
    this.displayBookingStatus = function (tag, user) {

        if (!this.allowBookingForm(user)) {
            ra.bookings.addTextTag(tag, 'div', "<b>You must be logged in to book places</b>");
        }
        if (this.options.payment_required) {
            this.displayPaymentDetails(tag);
        } else {
            ra.bookings.addTextTag(tag, 'div', "No payment required");
        }
        if (this.bookingClosed()) {
            return;
        }
        if (this.options.total_places === 0) {
            ra.bookings.addTextTag(tag, 'div', "Unlimited number of places");
        } else {
            ra.bookings.addTextTag(tag, 'div', "Total number of places available: " + this.options.total_places);
        }
        if (this.options.attendeetype === 'memonly') {
            ra.bookings.addTextTag(tag, 'div', "<b>This event is for MEMBERS ONLY</b>");
        } else {
            ra.bookings.addTextTag(tag, 'div', "Event is open to general public ");
        }


        var no = this.noAttendees();
        switch (no) {
            case 0:
                ra.bookings.addTextTag(tag, 'div', "There are no bookings so far");
                break;
            case this.options.total_places:
                if (this.options.waitinglist) {
                    ra.bookings.addTextTag(tag, 'div', "<b>This event is FULLY BOOKED</b> but you can join the waiting list.");
                } else {
                    ra.bookings.addTextTag(tag, 'div', "<b>This event is FULLY BOOKED</b>");
                }
                break;
            default:
                ra.bookings.addTextTag(tag, 'div', "There are currently " + no + " place(s) booked.");
        }
    };
    this.displayPaymentDetails = function (tag) {
        var tags = [
            {parent: 'root', tag: 'div', attrs: {class: 'ra bookings'}, innerHTML: 'A payment is required for this walk/event:'},
            {name: 'details', parent: 'root', tag: 'div', attrs: {class: 'booking howtopay'}},
            {parent: 'details', tag: 'div', innerHTML: 'How to pay'},
            {parent: 'details', tag: 'div', attrs: {class: 'bookingitem walkitem payment'}, style: {'margin-left': '10px'}, innerHTML: this.options.payment_details},
            {parent: 'root', tag: 'div', style: {clear: 'both'}}
        ];
        ra.html.generateTags(tag, tags);
    };
    this.getBooking = function (md5Email) {
        return  this.blc.isPresent(md5Email);
    };
    this.getWaiting = function (md5Email) {
        return this.wlc.isPresent(md5Email);
    };
    this.noAttendees = function () {
        return this.blc.noAttendees();
    };
    this.allowBookingForm = function (user) {
        var placesOkay = true;
        if (this.isFullyBooked()) {
            if (!this.options.waitinglist) {
                placesOkay = false;
            }
        }
        var userOkay = false;
        if (user.id > 0) { // user logged in
            userOkay = true;
        } else {
            if (this.options.guest) {
                userOkay = true;
            }
        }
        if (userOkay && placesOkay) {
            return true;
        }
        return false;
    };
    this.allowWaitingForm = function (user) {
        var placesOkay = true;
        if (this.isFullyBooked()) {
            if (!this.options.waitinglist) {
                placesOkay = false;
            }
        }
        var userOkay = false;
        if (user.id > 0) { // user logged in
            userOkay = true;
        } else {
            if (this.options.guest) {
                userOkay = true;
            }
        }
        if (userOkay && placesOkay) {
            return true;
        }
        return false;
    };
    this.bookingClosed = function () {
        if (this.actualClosingDate === null) {
            return false;
        }
        var d1 = ra.date.getDateTime(this.actualClosingDate);
        var d2 = new Date();
        return d2 > d1;
    };

    this.displayUserInfo = function (tag, userId) {
        if (this.bookingClosed()) {
            ra.bookings.addTextTag(tag, 'h3', "Sorry, booking is now closed");
            return;
        } else {
            if (this.actualClosingDate !== null) {
                ra.bookings.addTextTag(tag, 'div', "Booking closes at " + ra.time.HHMM(this.actualClosingDate) + " " + ra.date.dowddmmyyyy(this.actualClosingDate));
            }
        }
        if (userId === 0) {
            if (this.options.guest) {
                ra.bookings.addTextTag(tag, 'div', "Guests may book up to " + this.options.maxguestattendees + " place(s)");
            } else {
                ra.bookings.addTextTag(tag, 'div', "<b>You must be logged in to book places</b>");
            }
        } else {
            ra.bookings.addTextTag(tag, 'div', "Logged in users may book up to " + this.options.maxattendees + " place(s)");
        }
    };
    this.canDisplayBookingList = function (user) {

        if (this.blc.noAttendees() === 0) {
            return false;
        }
        if (user.canEdit) {
            return true;
        }
        if (user.id > 0) { // user logged in.
            return this.options.userlistvisibletousers;
        } else {
            return this.options.userlistvisibletoguests;
        }
    };
    this.canDisplayWaitingList = function (user) {
        if (!this.options.waitinglist) {
            return false;
        }
        return this.canDisplayBookingList(user);
    };
    this.setOverrides = function (defaults) {
        this.overrides = defaults.overrides(this.options);
    };
};
// Booking List Collection
ra.bookings.blc = function () {
    this.items = [];
    this.addItem = function (b) {
        this.items.push(b);
    };
    this.process = function (values) {
        values.forEach(value => {
            this.addItem(new ra.bookings.bli(value));
        });
    };
    this.noAttendees = function () {
        var no = 0;
        this.items.forEach(b => {
            no += b.noAttendees();
        });
        return no;
    };
    this.isPresent = function (md5Email) {
        for (let item of this.items) {
            if (item.isPresent(md5Email)) {
                return item;
            }
        }
        return null;
    };
    this.list = function (tag, options) {
        var tags = [
            {name: 'base', parent: 'root', tag: 'details'},
            {name: 'button', parent: 'base', tag: 'summary', attrs: {class: 'link-button tiny button mintcake'}, innerHTML: 'Bookings so far'},
            {name: 'canedit', parent: 'base', tag: 'div', style: {clear: 'both', "color": "#8A2716"}},
            {name: 'list', parent: 'base', tag: 'div', style: {clear: 'both'}}
        ];
        var elements = ra.html.generateTags(tag, tags);
        var format = [{"title": "Name", "options": {align: "left"}, field: {type: 'text', filter: false, sort: false}},
            {"title": "Status", "options": {align: "left"}},
            {"title": "Places", "options": {align: "left"}},
            {"title": "Member", "options": {align: "left", "style": {"color": "#8A2716"}}},
            {"title": "Telephone", "options": {align: "left", "style": {"color": "#8A2716"}}},
            {"title": "Paid", "options": {align: "left", "style": {"color": "#8A2716"}}},
            {"title": "Action", "options": {align: "right", "style": {"min-width": "60px", "color": "#8A2716"}}}];
        if (options.canEdit) {
            elements.canedit.innerHTML = "You are logged on with Booking Contact access and have additional options.";
            if (options.displayPaid) {
                elements.canedit.innerHTML += "<br>To record payments, click on the Paid field.";
            }
        }

        if (!options.canEdit) {
            format[3].ignore = true;
            format[4].ignore = true;
            format[5].ignore = true;
            format[6].ignore = true;
        } else {
            if (!options.displayPaid) {
                format[5].ignore = true;
            }
        }
        format[1].ignore = !options.guest;
        if (options.canEdit) {
            var emailallc = document.createElement("div");
            emailallc.innerHTML = "Email all those who have booked&nbsp;&nbsp;";
            emailallc.style.color = '#8A2716';
            elements.list.appendChild(emailallc);
            ra.bookings.displayEmailIcon(emailallc, "Email all those who have booked", tag, "AdminEmailAllBooking");
        }
        var table = new ra.paginatedTable(elements.list);
        table.tableHeading(format);
        this.items.forEach(item => {
            item.list(tag, table, format, options);
        });
        table.tableEnd();


    };
};

// Booking list item
ra.bookings.bli = function (value) {
    this.id = value.id;
    this.name = value.name;
    this.md5Email = value.md5Email;
    this.telephone = value.telephone;
    this.attendees = parseInt(value.noAttendees);
    this.member = value.member;
    this.paid = value.paid;
    this.isPresent = function (md5Email) {
        return md5Email === this.md5Email;
    };
    this.noAttendees = function () {
        return this.attendees;
    };
    this.list = function (eventTag, table, format, options) {

        table.tableRowStart();
        table.tableRowItem(this.name, format[0]);
        if (this.id > 0) {
            table.tableRowItem("Registered", format[1]);
        } else {
            table.tableRowItem("Guest", format[1]);
        }
        table.tableRowItem(this.attendees, format[2]);
        table.tableRowItem(this.member, format[3]);
        table.tableRowItem(this.telephone, format[4]);
        table.tableRowItem(this.getPaid(options, eventTag, this), format[5]);

        var self = this;
        var span = document.createElement("span");
        ra.bookings.displayDeleteIcon(span, "Delete this booking", eventTag, "deleteBooker", {user: self});
        ra.bookings.displayEmailIcon(span, "Email this user", eventTag, "emailSingleBooker", {user: self});
        table.tableRowItem(span, format[6]);

        table.tableRowEnd();
    };

    this.getPaid = function (options, eventTag, user) {
        var span = document.createElement("span");
        span.classList.add('ra', 'bookings', 'paid');
        span.innerHTML = this.paid;
        if (options.canEdit) {
            span.title = 'Paid - click to change';
            span.classList.add('edit');
            span.addEventListener('click', (e) => {
                let event = new Event('changePaid');
                event.raData = user;
                eventTag.dispatchEvent(event);
            });
        }
        return span;
    };
};
// Waiting/Notification list collection
ra.bookings.wlc = function () {
    this.items = [];
    this.addItem = function (wl) {
        this.items.push(wl);
    };
    this.process = function (values) {
        values.forEach(value => {
            this.addItem(new ra.bookings.wli(value));
        });
    };
    this.noWaiting = function () {
        return this.items.length;
    };
    this.isPresent = function (md5Email) {
        for (let item of this.items) {
            if (item.isPresent(md5Email)) {
                return item;
            }
        }
        return null;
    };
    this.list = function (tag, options) {
        if (this.items.length === 0) {
            return;
        }
        var tags = [
            {name: 'base', parent: 'root', tag: 'details'},
            {name: 'button', parent: 'base', tag: 'summary', attrs: {class: 'link-button tiny button mintcake'}, innerHTML: 'Notify me list'},
            {name: 'list', parent: 'base', tag: 'div', style: {clear: 'both'}}
        ];
        var format = [{"title": "Name", "options": {align: "left"}, field: {type: 'text', filter: false, sort: false}},
            {"title": "Status", "options": {align: "left"}},
            {"title": "Action", "options": {align: "right", "style": {"min-width": "60px", "color": "#8A2716"}}}];

        var elements = ra.html.generateTags(tag, tags);

        if (options.canEdit) {
            var emailallc = document.createElement("span");
            emailallc.innerHTML = "Email all those on waiting list";
            emailallc.style.color = '#8A2716';
            emailallc.style.paddingRight = "10px";
            elements.list.appendChild(emailallc);
            ra.bookings.displayEmailIcon(elements.list, "Email all those on list", tag, "AdminEmailAllWaiting");
        }

        if (!options.canEdit) {
            format[1].ignore = true;
            format[2].ignore = true;
        }

        var table = new ra.paginatedTable(elements.list);
        table.tableHeading(format);
        this.items.forEach(item => {
            item.list(tag, table, format);
        });
        table.tableEnd();

    };

};
// Waiting list item
ra.bookings.wli = function (value) {
    this.id = value.id;
    this.name = value.name;
    this.md5Email = value.md5Email;
    this.isPresent = function (md5Email) {
        return md5Email === this.md5Email;
    };
    this.list = function (eventTag, table, format) {


        table.tableRowStart();
        table.tableRowItem(this.name, format[0]);
        if (this.id > 0) {
            table.tableRowItem("Registered", format[1]);
        } else {
            table.tableRowItem("Guest", format[1]);
        }

        var self = this;
        var span = document.createElement("span");
        ra.bookings.displayDeleteIcon(span, "Delete this booking", eventTag, "deleteBooker", {user: self});
        ra.bookings.displayEmailIcon(span, "Email this user", eventTag, "emailSingleBooker", {user: self});
        table.tableRowItem(span, format[2]);

        table.tableRowEnd();

    };

};